<?php

declare(strict_types=1);

namespace HyperfTest\Cases\Http;

use App\Repository\CardOrmRepository;
use App\Repository\CardQueryBuilderRepository;
use App\Repository\CardRepositoryInterface;
use Hyperf\Context\ApplicationContext;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Hyperf\Testing\TestCase;

use function Hyperf\Support\env;

class CardApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Db::table('transactions')->delete();
        Db::table('cards')->delete();
    }

    public function testRepositoryBindingFollowsTheEnvironment(): void
    {
        $repository = ApplicationContext::getContainer()->get(CardRepositoryInterface::class);
        $driver = (string) env('CARD_REPOSITORY', 'orm');

        if ($driver === 'query_builder') {
            $this->assertInstanceOf(CardQueryBuilderRepository::class, $repository);
            return;
        }

        $this->assertInstanceOf(CardOrmRepository::class, $repository);
    }

    public function testSchemaDoesNotStorePanOrCvv(): void
    {
        $columns = Schema::getColumnListing('cards');

        $this->assertContains('token', $columns);
        $this->assertContains('bin', $columns);
        $this->assertContains('last_four', $columns);
        $this->assertNotContains('number', $columns);
        $this->assertNotContains('pan', $columns);
        $this->assertNotContains('cvv', $columns);
    }

    public function testCreateShowListUpdateAndDelete(): void
    {
        $created = $this->createCard();
        $this->assertSame('visa', $created['brand']);
        $this->assertSame('411111******1111', $created['masked_number']);
        $this->assertStringStartsWith('tok_', $created['token']);
        $this->assertArrayNotHasKey('number', $created);

        $show = $this->get('/cards/' . $created['id']);
        $show->assertOk();
        $this->assertSame($created['id'], $show->json('data.id'));

        $second = $this->createCard([
            'holder_name' => 'Grace Hopper',
            'number' => '5555555555554444',
        ]);
        $this->assertSame('mastercard', $second['brand']);

        $list = $this->get('/cards', ['brand' => 'visa', 'per_page' => 1]);
        $list->assertOk();
        $body = $list->json();
        $this->assertCount(1, $body['data']);
        $this->assertSame('visa', $body['data'][0]['brand']);
        $this->assertSame(1, $body['meta']['per_page']);
        $this->assertSame(1, $body['meta']['total']);

        $updated = $this->put('/cards/' . $created['id'], ['holder_name' => 'Grace Hopper']);
        $updated->assertOk();
        $this->assertSame('Grace Hopper', $updated->json('data.holder_name'));

        $patched = $this->patch('/cards/' . $created['id'], ['expiry_month' => 11, 'expiry_year' => 2031]);
        $patched->assertOk();
        $this->assertSame(11, $patched->json('data.expiry_month'));
        $this->assertSame(2031, $patched->json('data.expiry_year'));

        $this->delete('/cards/' . $created['id'])->assertNoContent();
        $this->get('/cards/' . $created['id'])->assertStatus(404);
        $this->get('/cards', ['brand' => 'visa'])->assertOk()->json();
        $afterDelete = $this->get('/cards', ['brand' => 'visa']);
        $afterDelete->assertOk();
        $this->assertSame(0, $afterDelete->json('meta.total'));

        $recreated = $this->createCard();
        $this->assertNotSame($created['id'], $recreated['id']);
    }

    public function testCreateRejectsCvvInvalidNumberUnknownBrandAndDuplicate(): void
    {
        $cvv = $this->json('/cards', $this->payload(['cvv' => '123']));
        $cvv->assertStatus(422);
        $this->assertStringContainsString('CVV', json_encode($cvv->json(), JSON_UNESCAPED_UNICODE));

        $this->json('/cards', $this->payload(['number' => '4111111111111112']))->assertStatus(422);
        $this->json('/cards', $this->payload(['number' => '9999990000000006']))->assertStatus(422);
        $this->json('/cards', $this->payload(['expiry_year' => 2020]))->assertStatus(422);

        $this->createCard(['number' => '4011780000000006', 'holder_name' => 'Elo Quatro']);
        $elo = $this->get('/cards', ['brand' => 'elo']);
        $elo->assertOk();
        $this->assertSame('elo', $elo->json('data.0.brand'));

        $this->json('/cards', $this->payload(['number' => '4011780000000006', 'holder_name' => 'Outra']))
            ->assertStatus(409);

        $this->get('/cards/999999')->assertStatus(404);
        $this->put('/cards/1', ['number' => '4111111111111111'])->assertStatus(422);
    }

    public function testTransactionEndpoints(): void
    {
        $card = $this->createCard(['number' => '4111111111110006', 'holder_name' => 'Pagadora']);

        $accepted = $this->json('/cards/' . $card['id'] . '/transactions', ['amount' => 10]);
        $accepted->assertAccepted();
        $transaction = $accepted->json('data');
        $this->assertSame('pending', $transaction['status']);
        $this->assertSame(1000, $transaction['amount_cents']);
        $this->assertSame('10.00', $transaction['amount']);
        $this->assertArrayNotHasKey('token', $transaction);
        $this->assertArrayNotHasKey('number', $transaction);

        $show = $this->get('/transactions/' . $transaction['id']);
        $show->assertOk();
        $this->assertSame($transaction['id'], $show->json('data.id'));
        $this->assertContains($show->json('data.status'), ['pending', 'approved']);

        $this->json('/cards/999999/transactions', ['amount' => 10])->assertStatus(404);
        $this->get('/transactions/999999')->assertStatus(404);
        $this->json('/cards/' . $card['id'] . '/transactions', [])->assertStatus(422);

        Db::table('cards')->where('id', $card['id'])->update([
            'expiry_month' => 1,
            'expiry_year' => 2020,
        ]);
        $this->json('/cards/' . $card['id'] . '/transactions', ['amount' => 10])->assertStatus(422);
    }

    private function createCard(array $override = []): array
    {
        $response = $this->json('/cards', $this->payload($override));
        $response->assertCreated();
        $body = $response->json();
        $encoded = json_encode($body, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString($this->payload($override)['number'], (string) $encoded);

        return $body['data'];
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'holder_name' => 'Ada Lovelace',
            'number' => '4111111111111111',
            'expiry_month' => 12,
            'expiry_year' => 2030,
        ], $override);
    }
}

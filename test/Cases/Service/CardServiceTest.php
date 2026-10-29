<?php

declare(strict_types=1);

namespace HyperfTest\Cases\Service;

use App\Exception\DomainException;
use App\Integration\VaultClientInterface;
use App\Repository\CardRepositoryInterface;
use App\Service\CardBrandDetector;
use App\Service\CardService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CardServiceTest extends TestCase
{
    private ArrayCardRepository $cards;

    private RecordingVault $vault;

    private CardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-29 12:00:00');
        $this->cards = new ArrayCardRepository();
        $this->vault = new RecordingVault();
        $this->service = new CardService($this->cards, $this->vault, new CardBrandDetector());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function testDetectsBrandsFromBin(): void
    {
        $detector = new CardBrandDetector();

        $this->assertSame('visa', $detector->detect('411111'));
        $this->assertSame('elo', $detector->detect('401178'));
        $this->assertSame('mastercard', $detector->detect('555555'));
        $this->assertSame('mastercard', $detector->detect('222100'));
        $this->assertSame('amex', $detector->detect('378282'));
        $this->assertSame('hipercard', $detector->detect('606282'));
        $this->assertSame('hipercard', $detector->detect('384100'));
        $this->assertNull($detector->detect('999999'));
    }

    public function testCreateDerivesBrandAndDoesNotKeepThePan(): void
    {
        $card = $this->service->create('Ada Lovelace', '4011780000000006', 12, 2030);

        $this->assertSame('elo', $card['brand']);
        $this->assertSame('401178', $card['bin']);
        $this->assertSame('0006', $card['last_four']);
        $this->assertSame('401178******0006', $card['masked_number']);
        $this->assertSame('tok_test', $card['token']);
        $this->assertArrayNotHasKey('number', $card);
        $this->assertSame(['4011780000000006'], $this->vault->pans);
        $this->assertArrayNotHasKey('number', $this->cards->stored[0]);
    }

    public function testRejectsInvalidLuhnBeforeCallingTheVault(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Número do cartão inválido.');

        try {
            $this->service->create('Ada Lovelace', '4111111111111112', 12, 2030);
        } finally {
            $this->assertSame([], $this->vault->pans);
        }
    }

    public function testRejectsExpiredCard(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cartão vencido.');

        $this->service->create('Ada Lovelace', '4111111111111111', 9, 2026);
    }

    public function testAcceptsCardThatExpiresThisMonth(): void
    {
        $card = $this->service->create('Ada Lovelace', '4111111111111111', 10, 2026);

        $this->assertSame('visa', $card['brand']);
    }

    public function testRejectsUnknownBrand(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('bandeira');

        $this->service->create('Ada Lovelace', '9999990000000006', 12, 2030);
    }

    public function testRejectsDuplicateFingerprint(): void
    {
        $this->service->create('Ada Lovelace', '4111111111111111', 12, 2030);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Já existe um cartão ativo');

        $this->service->create('Outra Pessoa', '4111111111111111', 12, 2030);
    }
}

final class RecordingVault implements VaultClientInterface
{
    public array $pans = [];

    public function tokenize(string $pan, int $expiryMonth, int $expiryYear): string
    {
        $this->pans[] = $pan;

        return 'tok_test';
    }
}

final class ArrayCardRepository implements CardRepositoryInterface
{
    public array $stored = [];

    public function create(array $attributes): array
    {
        $attributes['id'] = count($this->stored) + 1;
        $attributes['masked_number'] = $attributes['bin'] . '******' . $attributes['last_four'];
        $attributes['created_at'] = '2026-10-29 12:00:00';
        $attributes['updated_at'] = '2026-10-29 12:00:00';
        $this->stored[] = $attributes;

        return $attributes;
    }

    public function findById(int $id): ?array
    {
        foreach ($this->stored as $card) {
            if ($card['id'] === $id && empty($card['deleted'])) {
                return $card;
            }
        }

        return null;
    }

    public function paginate(?string $brand, int $page, int $perPage): array
    {
        return ['data' => [], 'meta' => ['current_page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1]];
    }

    public function update(int $id, array $attributes): ?array
    {
        return null;
    }

    public function delete(int $id): bool
    {
        return false;
    }

    public function existsDuplicate(
        string $bin,
        string $lastFour,
        int $expiryMonth,
        int $expiryYear,
        ?int $exceptId = null,
    ): bool {
        foreach ($this->stored as $card) {
            if (! empty($card['deleted'])) {
                continue;
            }
            if ($exceptId !== null && $card['id'] === $exceptId) {
                continue;
            }
            if ($card['bin'] === $bin && $card['last_four'] === $lastFour && $card['expiry_month'] === $expiryMonth && $card['expiry_year'] === $expiryYear) {
                return true;
            }
        }

        return false;
    }
}

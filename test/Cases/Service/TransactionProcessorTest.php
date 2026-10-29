<?php

declare(strict_types=1);

namespace HyperfTest\Cases\Service;

use App\Exception\GatewayException;
use App\Exception\RecoverableTransactionException;
use App\Integration\DeadLetterPublisherInterface;
use App\Integration\GatewayClientInterface;
use App\Model\TransactionStatus;
use App\Repository\TransactionRepositoryInterface;
use App\Service\TransactionProcessor;
use Hyperf\Contract\StdoutLoggerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Throwable;

class TransactionProcessorTest extends TestCase
{
    public function testApprovesAndDoesNotDeadLetter(): void
    {
        $repo = new MemoryTransactions();
        $repo->seed($this->pending(1000));
        $gateway = new ScriptedGateway([['status' => 'approved', 'reference' => 'gw_ok', 'reason' => null]]);
        $dead = new RecordingDeadLetter();
        $processor = $this->processor($repo, $gateway, $dead);

        $processor->handle(['idempotency_key' => 'key-1']);

        $this->assertSame(TransactionStatus::APPROVED, $repo->rows[1]['status']);
        $this->assertSame('gw_ok', $repo->rows[1]['gateway_reference']);
        $this->assertSame([], $dead->messages);
        $this->assertSame(1, $gateway->calls);
    }

    public function testDeclineIsFinalAndDoesNotRetry(): void
    {
        $repo = new MemoryTransactions();
        $repo->seed($this->pending(1099));
        $gateway = new ScriptedGateway([[
            'status' => 'declined',
            'reference' => 'gw_no',
            'reason' => 'recusado',
        ]]);
        $dead = new RecordingDeadLetter();
        $processor = $this->processor($repo, $gateway, $dead);

        $processor->handle(['idempotency_key' => 'key-1']);

        $this->assertSame(TransactionStatus::DECLINED, $repo->rows[1]['status']);
        $this->assertSame([], $dead->messages);
    }

    public function testRetriesThenSendsToDeadLetter(): void
    {
        $repo = new MemoryTransactions();
        $repo->seed($this->pending(1077));
        $gateway = new ScriptedGateway([new GatewayException('Falha simulada do adquirente.')]);
        $dead = new RecordingDeadLetter();
        $processor = $this->processor($repo, $gateway, $dead);

        try {
            $processor->handle(['idempotency_key' => 'key-1']);
            $this->fail('A primeira falha deveria pedir retentativa.');
        } catch (RecoverableTransactionException) {
            $this->assertSame(1, $repo->rows[1]['attempts']);
            $this->assertSame(TransactionStatus::PENDING, $repo->rows[1]['status']);
        }

        try {
            $processor->handle(['idempotency_key' => 'key-1']);
            $this->fail('A segunda falha deveria pedir retentativa.');
        } catch (RecoverableTransactionException) {
            $this->assertSame(2, $repo->rows[1]['attempts']);
        }

        $processor->handle(['idempotency_key' => 'key-1']);

        $this->assertSame(TransactionStatus::FAILED, $repo->rows[1]['status']);
        $this->assertCount(1, $dead->messages);
        $this->assertSame('key-1', $dead->messages[0]['idempotency_key']);
        $this->assertSame(3, $dead->messages[0]['attempts']);
        $this->assertArrayNotHasKey('token', $dead->messages[0]);
    }

    public function testIgnoresMessageAlreadyFinished(): void
    {
        $repo = new MemoryTransactions();
        $row = $this->pending(1000);
        $row['status'] = TransactionStatus::APPROVED;
        $repo->seed($row);
        $gateway = new ScriptedGateway([]);
        $processor = $this->processor($repo, $gateway, new RecordingDeadLetter());

        $processor->handle(['idempotency_key' => 'key-1']);

        $this->assertSame(0, $gateway->calls);
    }

    public function testUnknownKeyIsAckedWithoutSideEffects(): void
    {
        $repo = new MemoryTransactions();
        $gateway = new ScriptedGateway([]);
        $dead = new RecordingDeadLetter();
        $processor = $this->processor($repo, $gateway, $dead);

        $processor->handle(['idempotency_key' => 'nao-existe']);

        $this->assertSame(0, $gateway->calls);
        $this->assertSame([], $dead->messages);
    }

    private function processor(MemoryTransactions $repo, ScriptedGateway $gateway, RecordingDeadLetter $dead): TransactionProcessor
    {
        return new TransactionProcessor($repo, $gateway, $dead, new class extends AbstractLogger implements StdoutLoggerInterface {
            public function log($level, string|\Stringable $message, array $context = []): void
            {
            }
        });
    }

    private function pending(int $cents): array
    {
        return [
            'id' => 1,
            'card_id' => 1,
            'amount_cents' => $cents,
            'currency' => 'BRL',
            'status' => TransactionStatus::PENDING,
            'gateway_reference' => null,
            'failure_reason' => null,
            'idempotency_key' => 'key-1',
            'attempts' => 0,
            'token' => 'tok_abc',
            'created_at' => '2026-10-29 12:00:00',
            'updated_at' => '2026-10-29 12:00:00',
        ];
    }
}

final class MemoryTransactions implements TransactionRepositoryInterface
{
    public array $rows = [];

    public function seed(array $row): void
    {
        $this->rows[$row['id']] = $row;
    }

    public function create(array $attributes): array
    {
        return $attributes;
    }

    public function findById(int $id): ?array
    {
        return $this->rows[$id] ?? null;
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        foreach ($this->rows as $row) {
            if ($row['idempotency_key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    public function markResult(int $id, string $status, ?string $reference, ?string $reason): bool
    {
        if (! isset($this->rows[$id]) || $this->rows[$id]['status'] !== TransactionStatus::PENDING) {
            return false;
        }
        $this->rows[$id]['status'] = $status;
        $this->rows[$id]['gateway_reference'] = $reference;
        $this->rows[$id]['failure_reason'] = $reason;

        return true;
    }

    public function incrementAttempts(int $id): int
    {
        if (($this->rows[$id]['status'] ?? '') === TransactionStatus::PENDING) {
            $this->rows[$id]['attempts']++;
        }

        return (int) $this->rows[$id]['attempts'];
    }
}

final class ScriptedGateway implements GatewayClientInterface
{
    public int $calls = 0;

    public function __construct(private array $script)
    {
    }

    public function charge(string $token, int $amountCents, string $idempotencyKey): array
    {
        ++$this->calls;
        $next = $this->script[0] ?? new GatewayException('sem script');
        if ($next instanceof Throwable) {
            throw $next;
        }

        return $next;
    }
}

final class RecordingDeadLetter implements DeadLetterPublisherInterface
{
    public array $messages = [];

    public function publish(array $message): void
    {
        $this->messages[] = $message;
    }
}

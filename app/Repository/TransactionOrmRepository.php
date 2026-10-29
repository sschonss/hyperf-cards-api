<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Transaction;
use App\Model\TransactionStatus;
use Carbon\Carbon;

/**
 * Repositório da transação usa só o Model.
 * A comparação ORM versus Query Builder fica nos repositórios de cartão,
 * que é o ponto da aula. Aqui o fluxo assíncrono fica mais fácil de ler.
 */
class TransactionOrmRepository implements TransactionRepositoryInterface
{
    public function create(array $attributes): array
    {
        $transaction = Transaction::query()->create($attributes);

        return TransactionMapper::fromModel($transaction);
    }

    public function findById(int $id): ?array
    {
        $transaction = Transaction::query()->find($id);
        if (! $transaction instanceof Transaction) {
            return null;
        }

        return TransactionMapper::fromModel($transaction);
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $transaction = Transaction::query()->where('idempotency_key', $key)->first();
        if (! $transaction instanceof Transaction) {
            return null;
        }

        return TransactionMapper::fromModel($transaction);
    }

    public function markResult(int $id, string $status, ?string $reference, ?string $reason): bool
    {
        $updated = Transaction::query()
            ->where('id', $id)
            ->where('status', TransactionStatus::PENDING)
            ->update([
                'status' => $status,
                'gateway_reference' => $reference,
                'failure_reason' => $reason,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);

        return $updated > 0;
    }

    public function incrementAttempts(int $id): int
    {
        Transaction::query()
            ->where('id', $id)
            ->where('status', TransactionStatus::PENDING)
            ->increment('attempts');

        return (int) Transaction::query()->where('id', $id)->value('attempts');
    }
}

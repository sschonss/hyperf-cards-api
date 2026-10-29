<?php

declare(strict_types=1);

namespace App\Repository;

interface TransactionRepositoryInterface
{
    public function create(array $attributes): array;

    public function findById(int $id): ?array;

    public function findByIdempotencyKey(string $key): ?array;

    public function markResult(int $id, string $status, ?string $reference, ?string $reason): bool;

    public function incrementAttempts(int $id): int;
}

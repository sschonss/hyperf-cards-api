<?php

declare(strict_types=1);

namespace App\Integration;

interface TransactionPublisherInterface
{
    /**
     * @param array{idempotency_key: string, transaction_id: int} $message
     */
    public function publish(array $message): void;
}

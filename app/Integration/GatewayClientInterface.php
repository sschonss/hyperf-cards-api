<?php

declare(strict_types=1);

namespace App\Integration;

interface GatewayClientInterface
{
    /**
     * @return array{status: string, reference: string|null, reason: string|null}
     */
    public function charge(string $token, int $amountCents, string $idempotencyKey): array;
}

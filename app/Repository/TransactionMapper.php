<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Transaction;
use App\Support\Money;
use DateTimeInterface;

final class TransactionMapper
{
    public static function fromModel(Transaction $transaction): array
    {
        return self::make([
            'id' => (int) $transaction->id,
            'card_id' => (int) $transaction->card_id,
            'amount_cents' => (int) $transaction->amount_cents,
            'currency' => (string) $transaction->currency,
            'status' => (string) $transaction->status,
            'gateway_reference' => $transaction->gateway_reference,
            'failure_reason' => $transaction->failure_reason,
            'idempotency_key' => (string) $transaction->idempotency_key,
            'attempts' => (int) $transaction->attempts,
            'token' => (string) $transaction->token,
            'created_at' => self::date($transaction->created_at),
            'updated_at' => self::date($transaction->updated_at),
        ]);
    }

    /**
     * O que sai na API. O token fica no banco para o worker e não volta no JSON.
     */
    public static function toPublic(array $transaction): array
    {
        unset($transaction['token']);
        $transaction['amount'] = Money::format((int) $transaction['amount_cents']);

        return $transaction;
    }

    private static function make(array $transaction): array
    {
        return $transaction;
    }

    private static function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}

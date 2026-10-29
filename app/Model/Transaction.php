<?php

declare(strict_types=1);

namespace App\Model;

class Transaction extends Model
{
    protected ?string $table = 'transactions';

    protected array $fillable = [
        'card_id',
        'amount_cents',
        'currency',
        'status',
        'gateway_reference',
        'failure_reason',
        'idempotency_key',
        'attempts',
        'token',
    ];

    protected array $casts = [
        'id' => 'integer',
        'card_id' => 'integer',
        'amount_cents' => 'integer',
        'attempts' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

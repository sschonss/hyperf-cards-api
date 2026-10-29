<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\Database\Model\SoftDeletes;

/**
 * O modelo só conhece o que a tabela guarda.
 * PAN e CVV não estão em $fillable porque essas colunas não existem.
 */
class Card extends Model
{
    use SoftDeletes;

    protected ?string $table = 'cards';

    protected array $fillable = [
        'holder_name',
        'bin',
        'last_four',
        'brand',
        'expiry_month',
        'expiry_year',
        'token',
    ];

    protected array $casts = [
        'id' => 'integer',
        'expiry_month' => 'integer',
        'expiry_year' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected array $appends = [
        'masked_number',
    ];

    public function getMaskedNumberAttribute(): string
    {
        return self::mask((string) $this->bin, (string) $this->last_four);
    }

    /**
     * 411111******1111 — seis do BIN, máscara fixa, quatro finais.
     * O Query Builder não dispara accessor; ele chama este método na mão.
     */
    public static function mask(string $bin, string $lastFour): string
    {
        return $bin . '******' . $lastFour;
    }
}

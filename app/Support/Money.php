<?php

declare(strict_types=1);

namespace App\Support;

use App\Exception\DomainException;

/**
 * A API recebe reais com centavos e persiste centavos inteiros.
 * Evita gravar float. O comentário no README explica o arredondamento.
 */
final class Money
{
    public static function toCents(mixed $amount): int
    {
        if (is_string($amount)) {
            $amount = str_replace(',', '.', trim($amount));
        }

        if (! is_numeric($amount)) {
            throw new DomainException('Valor inválido.');
        }

        $cents = (int) round(((float) $amount) * 100);
        if ($cents < 1 || $cents > 99_999_999) {
            throw new DomainException('Valor fora do intervalo permitido.');
        }

        return $cents;
    }

    public static function format(int $cents): string
    {
        $reais = intdiv($cents, 100);
        $fraction = abs($cents % 100);

        return sprintf('%d.%02d', $reais, $fraction);
    }
}

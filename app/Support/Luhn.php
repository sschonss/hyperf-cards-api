<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Algoritmo de Luhn. Confere o dígito verificador do PAN.
 * Não armazena o número: só responde se a sequência é consistente.
 */
final class Luhn
{
    public static function isValid(string $number): bool
    {
        $length = strlen($number);
        if ($length < 13 || $length > 19 || preg_match('/^\d+$/', $number) !== 1) {
            return false;
        }

        $sum = 0;
        $alternate = false;
        for ($index = $length - 1; $index >= 0; --$index) {
            $digit = (int) $number[$index];
            if ($alternate) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $alternate = ! $alternate;
        }

        return $sum % 10 === 0;
    }
}

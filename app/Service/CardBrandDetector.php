<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Regras públicas e simplificadas de BIN, na ordem em que precisam ser testadas.
 *
 * Elo vem antes da Visa porque algumas faixas Elo começam com 4.
 * Hipercard vem antes do restante porque 606282 não cai nas outras.
 * Mastercard cobre 51-55 e a faixa 2-series 2221-2720.
 *
 * A lista Elo é um subconjunto didático das faixas publicadas por bibliotecas
 * abertas de detecção de bandeira. Não é a tabela completa da Elo.
 */
final class CardBrandDetector
{
    public const BRANDS = ['visa', 'mastercard', 'elo', 'amex', 'hipercard'];

    /**
     * @var list<array{0: int, 1: int}>
     */
    private const ELO_RANGES = [
        [401178, 401179],
        [431274, 431274],
        [438935, 438935],
        [451416, 451416],
        [457393, 457393],
        [457631, 457632],
        [504175, 504175],
        [506699, 506778],
        [509000, 509999],
        [627780, 627780],
        [636297, 636297],
        [636368, 636368],
        [650031, 650033],
        [650035, 650051],
        [650405, 650439],
        [650485, 650538],
        [650541, 650598],
        [650700, 650718],
        [650720, 650727],
        [650901, 650920],
        [651652, 651679],
        [655000, 655019],
        [655021, 655058],
    ];

    public function detect(string $bin): ?string
    {
        if (preg_match('/^\d{6}$/', $bin) !== 1) {
            return null;
        }

        if ($this->isElo($bin)) {
            return 'elo';
        }

        if (str_starts_with($bin, '606282') || str_starts_with($bin, '3841')) {
            return 'hipercard';
        }

        $prefix2 = (int) substr($bin, 0, 2);
        if ($prefix2 === 34 || $prefix2 === 37) {
            return 'amex';
        }

        $prefix4 = (int) substr($bin, 0, 4);
        if (($prefix2 >= 51 && $prefix2 <= 55) || ($prefix4 >= 2221 && $prefix4 <= 2720)) {
            return 'mastercard';
        }

        if (str_starts_with($bin, '4')) {
            return 'visa';
        }

        return null;
    }

    private function isElo(string $bin): bool
    {
        $prefix = (int) $bin;
        foreach (self::ELO_RANGES as [$start, $end]) {
            if ($prefix >= $start && $prefix <= $end) {
                return true;
            }
        }

        return false;
    }
}

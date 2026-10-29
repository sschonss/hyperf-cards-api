<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Card;
use DateTimeInterface;

/**
 * Traduz Model ou linha do Query Builder para o mesmo array.
 * Sem esta classe, o ORM devolveria accessor e o Query Builder não.
 */
final class CardMapper
{
    public static function fromModel(Card $card): array
    {
        return self::make([
            'id' => (int) $card->id,
            'holder_name' => (string) $card->holder_name,
            'bin' => (string) $card->bin,
            'last_four' => (string) $card->last_four,
            'brand' => (string) $card->brand,
            'expiry_month' => (int) $card->expiry_month,
            'expiry_year' => (int) $card->expiry_year,
            'token' => (string) $card->token,
            'masked_number' => (string) $card->masked_number,
            'created_at' => self::date($card->created_at),
            'updated_at' => self::date($card->updated_at),
        ]);
    }

    public static function fromRow(object $row): array
    {
        $bin = (string) $row->bin;
        $lastFour = (string) $row->last_four;

        return self::make([
            'id' => (int) $row->id,
            'holder_name' => (string) $row->holder_name,
            'bin' => $bin,
            'last_four' => $lastFour,
            'brand' => (string) $row->brand,
            'expiry_month' => (int) $row->expiry_month,
            'expiry_year' => (int) $row->expiry_year,
            'token' => (string) $row->token,
            'masked_number' => Card::mask($bin, $lastFour),
            'created_at' => self::date($row->created_at),
            'updated_at' => self::date($row->updated_at),
        ]);
    }

    public static function page(iterable $items, int $currentPage, int $perPage, int $total, int $lastPage, bool $models): array
    {
        $data = [];
        foreach ($items as $item) {
            $data[] = $models ? self::fromModel($item) : self::fromRow($item);
        }

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => $lastPage,
            ],
        ];
    }

    private static function make(array $card): array
    {
        return $card;
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

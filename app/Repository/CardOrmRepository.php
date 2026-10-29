<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Card;

/**
 * Implementação com o Model, no estilo Eloquent do Hyperf.
 * Soft delete, timestamps e o accessor masked_number vêm do Model.
 * Este arquivo não filtra deleted_at na mão: o trait SoftDeletes faz isso.
 */
class CardOrmRepository implements CardRepositoryInterface
{
    public function create(array $attributes): array
    {
        $card = Card::query()->create($attributes);

        return CardMapper::fromModel($card);
    }

    public function findById(int $id): ?array
    {
        $card = Card::query()->find($id);
        if (! $card instanceof Card) {
            return null;
        }

        return CardMapper::fromModel($card);
    }

    public function paginate(?string $brand, int $page, int $perPage): array
    {
        $query = Card::query()->orderByDesc('id');
        if ($brand !== null && $brand !== '') {
            $query->where('brand', $brand);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return CardMapper::page(
            $paginator->items(),
            $paginator->currentPage(),
            $paginator->perPage(),
            $paginator->total(),
            $paginator->lastPage(),
            true,
        );
    }

    public function update(int $id, array $attributes): ?array
    {
        $card = Card::query()->find($id);
        if (! $card instanceof Card) {
            return null;
        }

        $card->fill($attributes);
        $card->save();

        return CardMapper::fromModel($card);
    }

    public function delete(int $id): bool
    {
        $card = Card::query()->find($id);
        if (! $card instanceof Card) {
            return false;
        }

        return (bool) $card->delete();
    }

    public function existsDuplicate(
        string $bin,
        string $lastFour,
        int $expiryMonth,
        int $expiryYear,
        ?int $exceptId = null,
    ): bool {
        $query = Card::query()
            ->where('bin', $bin)
            ->where('last_four', $lastFour)
            ->where('expiry_month', $expiryMonth)
            ->where('expiry_year', $expiryYear);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }
}

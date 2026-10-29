<?php

declare(strict_types=1);

namespace App\Repository;

use Hyperf\DbConnection\Db;

/**
 * A mesma porta, agora com o Query Builder (Db::table).
 * Não há Model, então:
 * - timestamps são preenchidos aqui;
 * - deleted_at precisa do whereNull em toda leitura;
 * - o soft delete é um UPDATE escrito à mão;
 * - masked_number chama Card::mask(), porque não existe accessor.
 */
class CardQueryBuilderRepository implements CardRepositoryInterface
{
    public function create(array $attributes): array
    {
        $now = $this->now();
        $attributes['created_at'] = $now;
        $attributes['updated_at'] = $now;

        $id = Db::table('cards')->insertGetId($attributes);
        $row = Db::table('cards')->where('id', $id)->first();

        return CardMapper::fromRow($row);
    }

    public function findById(int $id): ?array
    {
        $row = Db::table('cards')->where('id', $id)->whereNull('deleted_at')->first();
        if ($row === null) {
            return null;
        }

        return CardMapper::fromRow($row);
    }

    public function paginate(?string $brand, int $page, int $perPage): array
    {
        $query = Db::table('cards')->whereNull('deleted_at')->orderByDesc('id');
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
            false,
        );
    }

    public function update(int $id, array $attributes): ?array
    {
        $existing = Db::table('cards')->where('id', $id)->whereNull('deleted_at')->first();
        if ($existing === null) {
            return null;
        }

        $attributes['updated_at'] = $this->now();
        Db::table('cards')->where('id', $id)->update($attributes);
        $row = Db::table('cards')->where('id', $id)->first();

        return CardMapper::fromRow($row);
    }

    public function delete(int $id): bool
    {
        $now = $this->now();
        $updated = Db::table('cards')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => $now,
                'updated_at' => $now,
            ]);

        return $updated > 0;
    }

    public function existsDuplicate(
        string $bin,
        string $lastFour,
        int $expiryMonth,
        int $expiryYear,
        ?int $exceptId = null,
    ): bool {
        $query = Db::table('cards')
            ->whereNull('deleted_at')
            ->where('bin', $bin)
            ->where('last_four', $lastFour)
            ->where('expiry_month', $expiryMonth)
            ->where('expiry_year', $expiryYear);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}

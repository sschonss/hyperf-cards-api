<?php

declare(strict_types=1);

namespace App\Repository;

/**
 * Contrato único do cartão. O CardService depende disto, não do Model nem do Db.
 * As duas implementações devolvem o mesmo array para o JSON não mudar com o driver.
 */
interface CardRepositoryInterface
{
    public function create(array $attributes): array;

    public function findById(int $id): ?array;

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(?string $brand, int $page, int $perPage): array;

    public function update(int $id, array $attributes): ?array;

    public function delete(int $id): bool;

    public function existsDuplicate(
        string $bin,
        string $lastFour,
        int $expiryMonth,
        int $expiryYear,
        ?int $exceptId = null,
    ): bool;
}

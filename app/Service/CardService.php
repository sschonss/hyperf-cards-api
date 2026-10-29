<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DomainException;
use App\Integration\VaultClientInterface;
use App\Repository\CardRepositoryInterface;
use App\Support\Luhn;
use Carbon\Carbon;

/**
 * Regras do cartão. Não sabe se por baixo existe Model ou Query Builder.
 * O PAN só existe nesta chamada: segue para o cofre e sai de escopo.
 */
class CardService
{
    public function __construct(
        private CardRepositoryInterface $cards,
        private VaultClientInterface $vault,
        private CardBrandDetector $brands,
    ) {
    }

    public function create(string $holderName, string $number, int $month, int $year): array
    {
        $holderName = trim($holderName);
        if (! Luhn::isValid($number)) {
            throw new DomainException('Número do cartão inválido.');
        }

        $this->assertExpiry($month, $year);

        $bin = substr($number, 0, 6);
        $lastFour = substr($number, -4);
        $brand = $this->brands->detect($bin);
        if ($brand === null) {
            throw new DomainException('Não foi possível identificar a bandeira a partir do BIN.');
        }

        if ($this->cards->existsDuplicate($bin, $lastFour, $month, $year)) {
            throw new DomainException(
                'Já existe um cartão ativo com o mesmo BIN, final e validade.',
                409
            );
        }

        $token = $this->vault->tokenize($number, $month, $year);
        // O PAN não entra no array persistido. A variável deixa de ser usada.
        unset($number);

        return $this->cards->create([
            'holder_name' => $holderName,
            'bin' => $bin,
            'last_four' => $lastFour,
            'brand' => $brand,
            'expiry_month' => $month,
            'expiry_year' => $year,
            'token' => $token,
        ]);
    }

    public function show(int $id): array
    {
        return $this->requireCard($id);
    }

    public function list(?string $brand, int $page, int $perPage): array
    {
        return $this->cards->paginate($brand, $page, $perPage);
    }

    public function update(int $id, array $attributes): array
    {
        $current = $this->requireCard($id);
        $changes = [];

        if (array_key_exists('holder_name', $attributes)) {
            $changes['holder_name'] = trim((string) $attributes['holder_name']);
        }

        $month = array_key_exists('expiry_month', $attributes)
            ? (int) $attributes['expiry_month']
            : (int) $current['expiry_month'];
        $year = array_key_exists('expiry_year', $attributes)
            ? (int) $attributes['expiry_year']
            : (int) $current['expiry_year'];

        if (array_key_exists('expiry_month', $attributes) || array_key_exists('expiry_year', $attributes)) {
            $this->assertExpiry($month, $year);
            if ($this->cards->existsDuplicate($current['bin'], $current['last_four'], $month, $year, $id)) {
                throw new DomainException(
                    'Já existe um cartão ativo com o mesmo BIN, final e validade.',
                    409
                );
            }
            $changes['expiry_month'] = $month;
            $changes['expiry_year'] = $year;
        }

        if ($changes === []) {
            throw new DomainException('Nada para atualizar.');
        }

        $updated = $this->cards->update($id, $changes);
        if ($updated === null) {
            throw new DomainException('Cartão não encontrado.', 404);
        }

        return $updated;
    }

    public function delete(int $id): void
    {
        if (! $this->cards->delete($id)) {
            throw new DomainException('Cartão não encontrado.', 404);
        }
    }

    private function requireCard(int $id): array
    {
        $card = $this->cards->findById($id);
        if ($card === null) {
            throw new DomainException('Cartão não encontrado.', 404);
        }

        return $card;
    }

    /**
     * O cartão vale até o último instante do mês de validade.
     */
    private function assertExpiry(int $month, int $year): void
    {
        if ($month < 1 || $month > 12) {
            throw new DomainException('Mês de validade inválido.');
        }

        $expiresAt = Carbon::create($year, $month, 1)->endOfMonth();
        if ($expiresAt->lt(Carbon::now())) {
            throw new DomainException('Cartão vencido.');
        }
    }
}

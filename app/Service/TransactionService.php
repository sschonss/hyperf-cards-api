<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\DomainException;
use App\Integration\TransactionPublisherInterface;
use App\Model\TransactionStatus;
use App\Repository\CardRepositoryInterface;
use App\Repository\TransactionMapper;
use App\Repository\TransactionRepositoryInterface;
use App\Support\Money;
use App\Support\Uuid;
use Carbon\Carbon;
use Throwable;

/**
 * Cria a transação pendente e publica a chave de idempotência.
 * Não chama o adquirente. Quem chama é o worker, depois da fila.
 */
class TransactionService
{
    public function __construct(
        private TransactionRepositoryInterface $transactions,
        private CardRepositoryInterface $cards,
        private TransactionPublisherInterface $publisher,
    ) {
    }

    public function create(int $cardId, mixed $amount, string $currency = 'BRL'): array
    {
        $card = $this->cards->findById($cardId);
        if ($card === null) {
            throw new DomainException('Cartão não encontrado.', 404);
        }

        $expiresAt = Carbon::create((int) $card['expiry_year'], (int) $card['expiry_month'], 1)->endOfMonth();
        if ($expiresAt->lt(Carbon::now())) {
            throw new DomainException('Cartão vencido.');
        }

        $cents = Money::toCents($amount);
        $key = Uuid::v4();
        $transaction = $this->transactions->create([
            'card_id' => $cardId,
            'amount_cents' => $cents,
            'currency' => strtoupper($currency),
            'status' => TransactionStatus::PENDING,
            'idempotency_key' => $key,
            'attempts' => 0,
            'token' => $card['token'],
        ]);

        try {
            $this->publisher->publish([
                'idempotency_key' => $key,
                'transaction_id' => $transaction['id'],
            ]);
        } catch (Throwable $exception) {
            $this->transactions->markResult(
                (int) $transaction['id'],
                TransactionStatus::FAILED,
                null,
                'Falha ao publicar na fila.'
            );
            throw new DomainException('Não foi possível enfileirar a transação.', 503);
        }

        return TransactionMapper::toPublic($transaction);
    }

    public function show(int $id): array
    {
        $transaction = $this->transactions->findById($id);
        if ($transaction === null) {
            throw new DomainException('Transação não encontrada.', 404);
        }

        return TransactionMapper::toPublic($transaction);
    }
}

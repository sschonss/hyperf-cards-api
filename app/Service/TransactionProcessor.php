<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\GatewayException;
use App\Exception\RecoverableTransactionException;
use App\Integration\DeadLetterPublisherInterface;
use App\Integration\GatewayClientInterface;
use App\Model\TransactionStatus;
use App\Repository\TransactionRepositoryInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Throwable;

use function Hyperf\Support\env;

/**
 * Roda no worker. É idempotente: status terminal não chama o adquirente de novo.
 * Recusa (declined) é resultado de negócio e não gera retentativa.
 * Erro técnico retenta até TRANSACTION_MAX_ATTEMPTS e então vai para a DLQ.
 */
class TransactionProcessor
{
    public function __construct(
        private TransactionRepositoryInterface $transactions,
        private GatewayClientInterface $gateway,
        private DeadLetterPublisherInterface $deadLetters,
        private StdoutLoggerInterface $logger,
    ) {
    }

    public function handle(array $message): void
    {
        $key = (string) ($message['idempotency_key'] ?? '');
        if ($key === '') {
            $this->deadLetters->publish([
                'error' => 'Mensagem sem idempotency_key.',
                'failed_at' => date('c'),
            ]);
            $this->logger->error('Mensagem inválida enviada para a DLQ.');

            return;
        }

        $transaction = $this->transactions->findByIdempotencyKey($key);
        if ($transaction === null) {
            $this->logger->warning("Mensagem {$key} ignorada: transação não está neste banco.");

            return;
        }

        if ($transaction['status'] !== TransactionStatus::PENDING) {
            $this->logger->info("Transação {$key} já está em {$transaction['status']}. Nada a fazer.");

            return;
        }

        if ($transaction['token'] === '') {
            $this->finishAsFailed($transaction, 0, 'Transação sem token.');

            return;
        }

        try {
            $result = $this->gateway->charge(
                (string) $transaction['token'],
                (int) $transaction['amount_cents'],
                $key
            );
        } catch (GatewayException $exception) {
            $this->retryOrFail($transaction, $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            $this->finishAsFailed($transaction, (int) $transaction['attempts'], 'Erro inesperado ao cobrar.');
            $this->logger->error($exception->getMessage());

            return;
        }

        $status = ($result['status'] ?? '') === TransactionStatus::DECLINED
            ? TransactionStatus::DECLINED
            : TransactionStatus::APPROVED;

        $this->transactions->markResult(
            (int) $transaction['id'],
            $status,
            $result['reference'] ?? null,
            $result['reason'] ?? null
        );
        $this->logger->info("Transação {$key} atualizada para {$status}.");
    }

    private function retryOrFail(array $transaction, string $reason): void
    {
        $attempts = $this->transactions->incrementAttempts((int) $transaction['id']);
        $max = max(1, (int) env('TRANSACTION_MAX_ATTEMPTS', 3));

        if ($attempts >= $max) {
            $this->finishAsFailed($transaction, $attempts, $reason);

            return;
        }

        $this->logger->warning("Transação {$transaction['idempotency_key']} falhou ({$attempts}/{$max}). Recolocando na fila.");
        throw new RecoverableTransactionException("Retentativa {$attempts} de {$max}.");
    }

    private function finishAsFailed(array $transaction, int $attempts, string $reason): void
    {
        $this->transactions->markResult(
            (int) $transaction['id'],
            TransactionStatus::FAILED,
            null,
            $reason
        );
        $this->deadLetters->publish([
            'idempotency_key' => $transaction['idempotency_key'],
            'transaction_id' => $transaction['id'],
            'attempts' => $attempts,
            'error' => $reason,
            'failed_at' => date('c'),
        ]);
        $this->logger->error("Transação {$transaction['idempotency_key']} foi para a DLQ. Motivo: {$reason}");
    }
}

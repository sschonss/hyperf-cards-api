<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Exception\RecoverableTransactionException;
use App\Integration\DeadLetterPublisherInterface;
use App\Service\TransactionProcessor;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Builder\QueueBuilder;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Message\Type;
use Hyperf\Amqp\Result;
use Hyperf\Contract\StdoutLoggerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

use function Hyperf\Support\env;

/**
 * Só o container worker liga este consumidor (AMQP_CONSUMER_ENABLED=true).
 * A API publica e não consome, para a aula ver os dois processos separados.
 */
#[Consumer(exchange: 'cards', routingKey: 'transactions', queue: 'card.transactions', name: 'TransactionConsumer', nums: 1)]
class TransactionConsumer extends ConsumerMessage
{
    protected Type|string $type = Type::DIRECT;

    public function __construct(
        private TransactionProcessor $processor,
        private DeadLetterPublisherInterface $deadLetters,
        private StdoutLoggerInterface $logger,
    ) {
    }

    public function isEnable(): bool
    {
        return filter_var(env('AMQP_CONSUMER_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function getQueueBuilder(): QueueBuilder
    {
        return (new QueueBuilder())
            ->setQueue($this->getQueue())
            ->setDurable(true)
            ->setArguments(new AMQPTable([
                'x-dead-letter-exchange' => 'cards.dlx',
                'x-dead-letter-routing-key' => 'transactions.failed',
            ]));
    }

    public function consumeMessage($data, AMQPMessage $message): Result
    {
        try {
            $this->processor->handle(is_array($data) ? $data : []);

            return Result::ACK;
        } catch (RecoverableTransactionException $exception) {
            $this->logger->warning($exception->getMessage());
            usleep(200000);

            return Result::REQUEUE;
        } catch (Throwable $exception) {
            $this->logger->error($exception->getMessage());
            $this->deadLetters->publish([
                'error' => 'Erro inesperado no consumidor.',
                'detail' => $exception->getMessage(),
                'failed_at' => date('c'),
            ]);

            return Result::ACK;
        }
    }
}

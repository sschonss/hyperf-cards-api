<?php

declare(strict_types=1);

namespace App\Integration;

use App\Amqp\Producer\TransactionProducerMessage;
use App\Exception\DomainException;
use Hyperf\Amqp\Producer;

class AmqpTransactionPublisher implements TransactionPublisherInterface
{
    public function __construct(private Producer $producer)
    {
    }

    public function publish(array $message): void
    {
        $published = $this->producer->produce(new TransactionProducerMessage($message));
        if ($published !== true) {
            throw new DomainException('Não foi possível publicar a transação na fila.', 503);
        }
    }
}

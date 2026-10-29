<?php

declare(strict_types=1);

namespace App\Integration;

use App\Amqp\Producer\DeadLetterProducerMessage;
use Hyperf\Amqp\Producer;
use RuntimeException;

class AmqpDeadLetterPublisher implements DeadLetterPublisherInterface
{
    public function __construct(private Producer $producer)
    {
    }

    public function publish(array $message): void
    {
        $published = $this->producer->produce(new DeadLetterProducerMessage($message));
        if ($published !== true) {
            throw new RuntimeException('Não foi possível publicar na DLQ.');
        }
    }
}

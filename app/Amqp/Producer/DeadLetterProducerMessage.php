<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Constants;
use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;

#[Producer(exchange: 'cards.dlx', routingKey: 'transactions.failed')]
class DeadLetterProducerMessage extends ProducerMessage
{
    protected Type|string $type = Type::DIRECT;

    protected array $properties = [
        'content_type' => 'application/json',
        'delivery_mode' => Constants::DELIVERY_MODE_PERSISTENT,
    ];

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }
}

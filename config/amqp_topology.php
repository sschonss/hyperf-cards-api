<?php

declare(strict_types=1);

/**
 * Nomes da topologia do RabbitMQ.
 * O script bin/declare-topology.php e as classes AMQP leem este arquivo
 * para não divergir exchange, fila e routing key.
 */
return [
    'exchange' => 'cards',
    'exchange_type' => 'direct',
    'routing_key' => 'transactions',
    'queue' => 'card.transactions',
    'dead_letter_exchange' => 'cards.dlx',
    'dead_letter_routing_key' => 'transactions.failed',
    'dead_letter_queue' => 'card.transactions.dlq',
];

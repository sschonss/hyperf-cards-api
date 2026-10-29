<?php

declare(strict_types=1);

/**
 * Declara exchange, fila de trabalho e dead-letter queue.
 * Roda no entrypoint, antes do servidor, para a mensagem não se perder
 * se o produtor publicar antes do consumidor subir.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

/** @var array<string, string> $topology */
$topology = require dirname(__DIR__) . '/config/amqp_topology.php';

$connection = new AMQPStreamConnection(
    getenv('AMQP_HOST') ?: 'rabbitmq',
    (int) (getenv('AMQP_PORT') ?: 5672),
    getenv('AMQP_USER') ?: 'cards',
    getenv('AMQP_PASSWORD') ?: 'cards',
    getenv('AMQP_VHOST') ?: '/'
);

$channel = $connection->channel();

$channel->exchange_declare($topology['exchange'], $topology['exchange_type'], false, true, false);
$channel->exchange_declare($topology['dead_letter_exchange'], $topology['exchange_type'], false, true, false);

$channel->queue_declare($topology['dead_letter_queue'], false, true, false, false);
$channel->queue_bind(
    $topology['dead_letter_queue'],
    $topology['dead_letter_exchange'],
    $topology['dead_letter_routing_key']
);

$channel->queue_declare(
    $topology['queue'],
    false,
    true,
    false,
    false,
    false,
    new AMQPTable([
        'x-dead-letter-exchange' => $topology['dead_letter_exchange'],
        'x-dead-letter-routing-key' => $topology['dead_letter_routing_key'],
    ])
);
$channel->queue_bind($topology['queue'], $topology['exchange'], $topology['routing_key']);

$channel->close();
$connection->close();

fwrite(STDOUT, "Topologia declarada: {$topology['queue']} e {$topology['dead_letter_queue']}.\n");

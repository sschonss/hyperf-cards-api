<?php

declare(strict_types=1);

use Hyperf\Amqp\IO\IOFactory;

use function Hyperf\Support\env;

return [
    'enable' => true,
    'default' => [
        'host' => env('AMQP_HOST', 'rabbitmq'),
        'port' => (int) env('AMQP_PORT', 5672),
        'user' => env('AMQP_USER', 'cards'),
        'password' => env('AMQP_PASSWORD', 'cards'),
        'vhost' => env('AMQP_VHOST', '/'),
        'open_ssl' => false,
        'concurrent' => [
            'limit' => 1,
        ],
        'pool' => [
            'connections' => 2,
        ],
        'io' => IOFactory::class,
        'params' => [
            'insist' => false,
            'login_method' => 'AMQPLAIN',
            'login_response' => null,
            'locale' => 'en_US',
            'connection_timeout' => 3.0,
            // Precisa ser maior que o heartbeat. Uma chamada lenta ao adquirente
            // não pode derrubar o consumidor no meio da tentativa.
            'read_write_timeout' => 120.0,
            'context' => null,
            'keepalive' => true,
            'heartbeat' => 60,
            'channel_rpc_timeout' => 0.0,
            'close_on_destruct' => false,
            'max_idle_channels' => 10,
            'connection_name' => null,
        ],
    ],
];

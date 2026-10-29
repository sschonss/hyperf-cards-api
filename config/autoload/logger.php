<?php

declare(strict_types=1);

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

$formatter = [
    'class' => LineFormatter::class,
    'constructor' => [
        'format' => null,
        'dateFormat' => 'Y-m-d H:i:s',
        'allowInlineLineBreaks' => true,
    ],
];

return [
    'default' => 'default',
    'channels' => [
        'default' => [
            'handler' => [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => BASE_PATH . '/runtime/logs/hyperf.log',
                    'level' => Level::Debug,
                ],
            ],
            'formatter' => $formatter,
        ],
        'sql' => [
            'handler' => [
                'class' => StreamHandler::class,
                'constructor' => [
                    'stream' => BASE_PATH . '/runtime/logs/sql.log',
                    'level' => Level::Debug,
                ],
            ],
            'formatter' => [
                'class' => LineFormatter::class,
                'constructor' => [
                    'format' => "[%datetime%] %message%\n",
                    'dateFormat' => 'Y-m-d H:i:s',
                    'allowInlineLineBreaks' => true,
                ],
            ],
        ],
    ],
];

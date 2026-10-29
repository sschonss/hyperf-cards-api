<?php

declare(strict_types=1);

use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;

use function Hyperf\Support\env;

$testing = env('APP_ENV', 'dev') === 'testing';

return [
    'app_name' => env('APP_NAME', 'cards-api'),
    'app_env' => env('APP_ENV', 'dev'),
    // PHPUnit transforma value="false" em string vazia. filter_var cobre
    // "", "false", "0" e o booleano que o env() já devolve.
    'scan_cacheable' => filter_var(env('SCAN_CACHEABLE', false), FILTER_VALIDATE_BOOLEAN),
    StdoutLoggerInterface::class => [
        'log_level' => $testing
            ? [LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::EMERGENCY, LogLevel::ERROR]
            : [
                LogLevel::ALERT,
                LogLevel::CRITICAL,
                LogLevel::DEBUG,
                LogLevel::EMERGENCY,
                LogLevel::ERROR,
                LogLevel::INFO,
                LogLevel::NOTICE,
                LogLevel::WARNING,
            ],
    ],
];

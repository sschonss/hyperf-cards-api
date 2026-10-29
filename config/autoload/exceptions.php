<?php

declare(strict_types=1);

use App\Exception\Handler\AppExceptionHandler;
use App\Exception\Handler\DomainExceptionHandler;
use App\Exception\Handler\HttpJsonExceptionHandler;
use App\Exception\Handler\ValidationExceptionHandler;

return [
    'handler' => [
        'http' => [
            ValidationExceptionHandler::class,
            DomainExceptionHandler::class,
            HttpJsonExceptionHandler::class,
            AppExceptionHandler::class,
        ],
    ],
];

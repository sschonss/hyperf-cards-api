<?php

declare(strict_types=1);

use Hyperf\Cache\Driver\MemoryDriver;

return [
    'default' => 'default',
    'stores' => [
        'default' => [
            'driver' => MemoryDriver::class,
            'prefix' => 'c:',
        ],
    ],
];

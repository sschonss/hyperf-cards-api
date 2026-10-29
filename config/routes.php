<?php

declare(strict_types=1);

use App\Controller\CardController;
use App\Controller\HealthController;
use App\Controller\TransactionController;
use Hyperf\HttpServer\Router\Router;

Router::get('/', [HealthController::class, 'index']);
Router::get('/health', [HealthController::class, 'health']);

Router::post('/cards', [CardController::class, 'store']);
Router::get('/cards', [CardController::class, 'index']);
Router::get('/cards/{id:\d+}', [CardController::class, 'show']);
Router::put('/cards/{id:\d+}', [CardController::class, 'update']);
Router::patch('/cards/{id:\d+}', [CardController::class, 'update']);
Router::delete('/cards/{id:\d+}', [CardController::class, 'destroy']);

Router::post('/cards/{id:\d+}/transactions', [TransactionController::class, 'store']);
Router::get('/transactions/{id:\d+}', [TransactionController::class, 'show']);

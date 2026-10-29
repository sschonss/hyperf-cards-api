<?php

declare(strict_types=1);

/**
 * Espera MySQL e RabbitMQ aceitarem conexão antes de subir o Hyperf.
 * Não usa o container do framework: só PDO e php-amqplib.
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

$host = getenv('DB_HOST') ?: 'mysql';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'cards';
$pass = getenv('DB_PASSWORD') ?: 'cards';
$amqpHost = getenv('AMQP_HOST') ?: 'rabbitmq';
$amqpPort = (int) (getenv('AMQP_PORT') ?: 5672);
$amqpUser = getenv('AMQP_USER') ?: 'cards';
$amqpPass = getenv('AMQP_PASSWORD') ?: 'cards';
$amqpVhost = getenv('AMQP_VHOST') ?: '/';

for ($attempt = 1; $attempt <= 60; ++$attempt) {
    $databaseReady = false;
    $brokerReady = false;

    try {
        new PDO(
            sprintf('mysql:host=%s;port=%s', $host, $port),
            $user,
            $pass,
            [PDO::ATTR_TIMEOUT => 2]
        );
        $databaseReady = true;
    } catch (Throwable $exception) {
        fwrite(STDERR, "MySQL ainda não respondeu ({$attempt}/60): {$exception->getMessage()}\n");
    }

    try {
        $connection = new AMQPStreamConnection(
            $amqpHost,
            $amqpPort,
            $amqpUser,
            $amqpPass,
            $amqpVhost,
            false,
            'AMQPLAIN',
            null,
            'en_US',
            3.0,
            3.0
        );
        $connection->close();
        $brokerReady = true;
    } catch (Throwable $exception) {
        fwrite(STDERR, "RabbitMQ ainda não respondeu ({$attempt}/60): {$exception->getMessage()}\n");
    }

    if ($databaseReady && $brokerReady) {
        fwrite(STDOUT, "MySQL e RabbitMQ prontos.\n");
        exit(0);
    }

    sleep(1);
}

fwrite(STDERR, "Dependências não ficaram prontas a tempo.\n");
exit(1);

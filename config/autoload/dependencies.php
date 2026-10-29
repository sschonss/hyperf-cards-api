<?php

declare(strict_types=1);

use App\Amqp\JsonPacker;
use App\Integration\AmqpDeadLetterPublisher;
use App\Integration\AmqpTransactionPublisher;
use App\Integration\DeadLetterPublisherInterface;
use App\Integration\GatewayClientInterface;
use App\Integration\HttpGatewayClient;
use App\Integration\HttpVaultClient;
use App\Integration\TransactionPublisherInterface;
use App\Integration\VaultClientInterface;
use App\Repository\CardOrmRepository;
use App\Repository\CardQueryBuilderRepository;
use App\Repository\CardRepositoryInterface;
use App\Repository\TransactionOrmRepository;
use App\Repository\TransactionRepositoryInterface;
use Hyperf\Amqp\Packer\Packer;

use function Hyperf\Support\env;

$driver = (string) env('CARD_REPOSITORY', 'orm');
$cardRepository = match ($driver) {
    'orm' => CardOrmRepository::class,
    'query_builder' => CardQueryBuilderRepository::class,
    default => throw new \RuntimeException(
        "CARD_REPOSITORY inválido: {$driver}. Use orm ou query_builder."
    ),
};

return [
    CardRepositoryInterface::class => $cardRepository,
    TransactionRepositoryInterface::class => TransactionOrmRepository::class,
    VaultClientInterface::class => HttpVaultClient::class,
    GatewayClientInterface::class => HttpGatewayClient::class,
    TransactionPublisherInterface::class => AmqpTransactionPublisher::class,
    DeadLetterPublisherInterface::class => AmqpDeadLetterPublisher::class,
    Packer::class => JsonPacker::class,
];

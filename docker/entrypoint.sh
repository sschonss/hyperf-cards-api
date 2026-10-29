#!/bin/sh
set -eu

cd /opt/www

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p runtime/logs runtime/container

echo "Aguardando MySQL e RabbitMQ..."
php bin/wait-for-deps.php

echo "Declarando a topologia do RabbitMQ..."
php bin/declare-topology.php

echo "Iniciando o Hyperf (AMQP_CONSUMER_ENABLED=${AMQP_CONSUMER_ENABLED:-false})."
exec php bin/hyperf.php start

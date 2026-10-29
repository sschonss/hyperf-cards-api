<?php

declare(strict_types=1);

namespace App\Amqp;

use Hyperf\Amqp\Packer\Packer;
use JsonException;

/**
 * JSON em vez do serialize padrão, para a mensagem aparecer legível
 * na interface de gerenciamento do RabbitMQ.
 */
class JsonPacker implements Packer
{
    public function pack(mixed $data): string
    {
        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return '{}';
        }
    }

    public function unpack(string $data): mixed
    {
        try {
            return json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }
    }
}

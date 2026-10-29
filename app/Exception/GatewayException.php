<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Falha transitória ao falar com o adquirente (timeout, conexão ou HTTP 5xx).
 * O consumidor trata isto como retentativa, não como recusa de negócio.
 */
class GatewayException extends RuntimeException
{
}

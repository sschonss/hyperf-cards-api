<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Erro de regra de negócio com o status HTTP que o controller deve devolver.
 * O controller não monta essa resposta: o handler faz isso.
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}

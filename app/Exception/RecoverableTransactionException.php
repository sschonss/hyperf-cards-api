<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * Sinal para o consumidor recolocar a mensagem na fila.
 * Não é um erro HTTP: a API já respondeu 202 antes disso.
 */
class RecoverableTransactionException extends RuntimeException
{
}

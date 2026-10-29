<?php

declare(strict_types=1);

namespace App\Integration;

interface VaultClientInterface
{
    /**
     * Envia o PAN ao cofre e devolve só o token.
     * Quem implementa não pode logar o número.
     */
    public function tokenize(string $pan, int $expiryMonth, int $expiryYear): string;
}

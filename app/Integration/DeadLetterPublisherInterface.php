<?php

declare(strict_types=1);

namespace App\Integration;

interface DeadLetterPublisherInterface
{
    public function publish(array $message): void;
}

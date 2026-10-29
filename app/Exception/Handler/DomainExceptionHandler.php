<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use App\Exception\DomainException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class DomainExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();
        /** @var DomainException $throwable */
        $payload = ['message' => $throwable->getMessage()];
        if ($throwable->errors !== []) {
            $payload['errors'] = $throwable->errors;
        }

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($throwable->status)
            ->withBody(new SwooleStream(json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof DomainException;
    }
}

<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class ValidationExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();
        /** @var ValidationException $throwable */
        $payload = [
            'message' => 'Os dados enviados são inválidos.',
            'errors' => $throwable->errors(),
        ];

        return $this->json($response, 422, $payload);
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof ValidationException;
    }

    private function json(ResponseInterface $response, int $status, array $payload): ResponseInterface
    {
        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status)
            ->withBody(new SwooleStream(json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }
}

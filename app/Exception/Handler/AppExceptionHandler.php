<?php

declare(strict_types=1);

namespace App\Exception\Handler;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Último handler. A resposta não leva stack trace nem corpo da requisição,
 * porque o corpo pode ter trazido um PAN na criação do cartão.
 */
class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(protected StdoutLoggerInterface $logger)
    {
    }

    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        $this->stopPropagation();
        $this->logger->error(sprintf(
            '%s [%s] em %s',
            $throwable->getMessage(),
            $throwable->getLine(),
            $throwable->getFile()
        ));

        $payload = ['message' => 'Erro interno.'];

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus(500)
            ->withBody(new SwooleStream(json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Integration;

use App\Exception\GatewayException;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Guzzle\ClientFactory;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\env;

/**
 * Cliente do adquirente. 4xx não retenta. 5xx e timeout retentam.
 * A recusa de negócio vem em HTTP 200 com status declined.
 */
class HttpGatewayClient implements GatewayClientInterface
{
    public function __construct(private ClientFactory $clientFactory)
    {
    }

    public function charge(string $token, int $amountCents, string $idempotencyKey): array
    {
        try {
            $response = $this->send(function () use ($token, $amountCents, $idempotencyKey) {
                return $this->client()->post('/transactions', [
                    'json' => [
                        'token' => $token,
                        'amount_cents' => $amountCents,
                        'idempotency_key' => $idempotencyKey,
                    ],
                ]);
            });
        } catch (GuzzleException $exception) {
            throw new GatewayException('Falha de rede ao chamar o adquirente.');
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if ($status >= 500) {
            $message = is_array($body) ? (string) ($body['message'] ?? 'Adquirente indisponível.') : 'Adquirente indisponível.';
            throw new GatewayException($message);
        }

        if ($status >= 400 || ! is_array($body) || ! isset($body['status'])) {
            throw new GatewayException('Resposta inesperada do adquirente.');
        }

        return [
            'status' => (string) $body['status'],
            'reference' => isset($body['reference']) ? (string) $body['reference'] : null,
            'reason' => isset($body['reason']) ? (string) $body['reason'] : null,
        ];
    }

    private function client(): \GuzzleHttp\Client
    {
        return $this->clientFactory->create([
            'base_uri' => rtrim((string) env('GATEWAY_BASE_URL', 'http://gateway:8080'), '/') . '/',
            'timeout' => (float) env('GATEWAY_TIMEOUT', 2),
            'connect_timeout' => 1.0,
            'http_errors' => false,
        ]);
    }

    /**
     * @param callable(): ResponseInterface $request
     */
    private function send(callable $request): ResponseInterface
    {
        $attempts = max(1, (int) env('HTTP_RETRY_ATTEMPTS', 3));
        $last = null;

        for ($attempt = 1; $attempt <= $attempts; ++$attempt) {
            try {
                $response = $request();
                if ($response->getStatusCode() >= 500 && $attempt < $attempts) {
                    $this->pause($attempt);
                    continue;
                }

                return $response;
            } catch (GuzzleException $exception) {
                $last = $exception;
                if ($attempt >= $attempts) {
                    throw $exception;
                }
                $this->pause($attempt);
            }
        }

        throw $last ?? new GatewayException('Adquirente indisponível.');
    }

    private function pause(int $attempt): void
    {
        usleep(100000 * $attempt);
    }
}

<?php

declare(strict_types=1);

namespace App\Integration;

use App\Exception\DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Guzzle\ClientFactory;
use Psr\Http\Message\ResponseInterface;

use function Hyperf\Support\env;

/**
 * Cliente HTTP do cofre. Timeout curto e poucas tentativas.
 * O corpo com o PAN não é escrito em log.
 */
class HttpVaultClient implements VaultClientInterface
{
    public function __construct(private ClientFactory $clientFactory)
    {
    }

    public function tokenize(string $pan, int $expiryMonth, int $expiryYear): string
    {
        try {
            $response = $this->send(function () use ($pan, $expiryMonth, $expiryYear) {
                return $this->client()->post('/tokenize', [
                    'json' => [
                        'number' => $pan,
                        'expiry_month' => $expiryMonth,
                        'expiry_year' => $expiryYear,
                    ],
                ]);
            });
        } catch (GuzzleException $exception) {
            throw new DomainException('Cofre indisponível.', 502);
        }

        $status = $response->getStatusCode();
        $body = json_decode((string) $response->getBody(), true);
        if ($status >= 200 && $status < 300 && is_array($body) && isset($body['token'])) {
            return (string) $body['token'];
        }

        $message = is_array($body) ? (string) ($body['message'] ?? 'O cofre recusou o cartão.') : 'O cofre recusou o cartão.';
        throw new DomainException($message, $status >= 400 && $status < 500 ? 422 : 502);
    }

    private function client(): \GuzzleHttp\Client
    {
        return $this->clientFactory->create([
            'base_uri' => rtrim((string) env('VAULT_BASE_URL', 'http://vault:8080'), '/') . '/',
            'timeout' => (float) env('VAULT_TIMEOUT', 2),
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

        throw $last ?? new DomainException('Cofre indisponível.', 502);
    }

    private function pause(int $attempt): void
    {
        usleep(100000 * $attempt);
    }
}

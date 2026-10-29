<?php

declare(strict_types=1);

/**
 * Mock didático com dois papéis, escolhidos pela variável ROLE:
 * - vault: cofre PCI. É o único processo que persiste o PAN.
 * - gateway: adquirente. Aprova, recusa ou falha conforme o valor.
 *
 * O servidor embutido do PHP é de propósito: um arquivo só, sem framework,
 * para a aula olhar a regra e não a infraestrutura.
 */

$role = getenv('ROLE') ?: '';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($path === '/health') {
    respond(200, ['status' => 'ok', 'role' => $role]);
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (! is_array($payload)) {
    respond(400, ['message' => 'JSON inválido.']);
}

if ($role === 'vault') {
    handleVault($method, $path, $payload);
}

if ($role === 'gateway') {
    handleGateway($method, $path, $payload);
}

respond(500, ['message' => 'ROLE deve ser vault ou gateway.']);

function handleVault(string $method, string $path, array $payload): void
{
    if ($method !== 'POST' || $path !== '/tokenize') {
        respond(404, ['message' => 'Rota não encontrada no cofre.']);
    }

    foreach (['cvv', 'cvc', 'security_code'] as $secret) {
        if (array_key_exists($secret, $payload)) {
            respond(422, ['message' => 'O cofre recusa CVV. Esse dado não pode ser armazenado.']);
        }
    }

    $number = preg_replace('/\D/', '', (string) ($payload['number'] ?? '')) ?? '';
    $month = (int) ($payload['expiry_month'] ?? 0);
    $year = (int) ($payload['expiry_year'] ?? 0);

    if (! luhn($number) || $month < 1 || $month > 12 || $year < 2000) {
        respond(422, ['message' => 'PAN ou validade rejeitados pelo cofre.']);
    }

    $token = 'tok_' . bin2hex(random_bytes(16));
    $store = readStore();
    $store[$token] = [
        'number' => $number,
        'expiry_month' => $month,
        'expiry_year' => $year,
        'created_at' => gmdate('c'),
    ];
    writeStore($store);

    $lastFour = substr($number, -4);
    mockLog("cofre: token emitido, final {$lastFour}");

    respond(201, ['token' => $token]);
}

function handleGateway(string $method, string $path, array $payload): void
{
    if ($method !== 'POST' || $path !== '/transactions') {
        respond(404, ['message' => 'Rota não encontrada no adquirente.']);
    }

    $token = (string) ($payload['token'] ?? '');
    $idempotencyKey = (string) ($payload['idempotency_key'] ?? '');
    $amountCents = (int) ($payload['amount_cents'] ?? 0);

    if ($token === '' || $idempotencyKey === '' || $amountCents < 1) {
        respond(400, ['message' => 'token, amount_cents e idempotency_key são obrigatórios.']);
    }

    $store = readStore();
    if (isset($store[$idempotencyKey])) {
        $saved = $store[$idempotencyKey];
        $replayStatus = $saved['body']['status'] ?? $saved['http_status'];
        mockLog("adquirente: replay {$idempotencyKey} status {$replayStatus}");
        respond((int) $saved['http_status'], $saved['body']);
    }

    $latency = (int) (getenv('GATEWAY_LATENCY_MS') ?: 0);
    if ($latency > 0) {
        usleep($latency * 1000);
    }

    $ending = $amountCents % 100;
    if ($ending === 66) {
        $slow = (int) (getenv('GATEWAY_SLOW_MS') ?: 4000);
        mockLog("adquirente: atraso de {$slow}ms e erro para valor terminado em 66");
        usleep($slow * 1000);
        $status = 500;
        $body = ['message' => 'Timeout simulado do adquirente.'];
    } elseif ($ending === 77) {
        mockLog("adquirente: HTTP 500 para valor terminado em 77");
        $status = 500;
        $body = ['message' => 'Falha simulada do adquirente.'];
    } elseif ($ending === 99) {
        $status = 200;
        $body = [
            'status' => 'declined',
            'reference' => 'gw_' . bin2hex(random_bytes(6)),
            'reason' => 'Valor terminado em 99 é recusado pelo adquirente simulado.',
        ];
        mockLog("adquirente: recusou {$idempotencyKey}");
    } else {
        $status = 200;
        $body = [
            'status' => 'approved',
            'reference' => 'gw_' . bin2hex(random_bytes(6)),
            'reason' => null,
        ];
        mockLog("adquirente: aprovou {$idempotencyKey}");
    }

    $store[$idempotencyKey] = [
        'http_status' => $status,
        'body' => $body,
    ];
    writeStore($store);
    respond($status, $body);
}

function luhn(string $number): bool
{
    $length = strlen($number);
    if ($length < 13 || $length > 19) {
        return false;
    }

    $sum = 0;
    $alternate = false;
    for ($index = $length - 1; $index >= 0; --$index) {
        $digit = (int) $number[$index];
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $alternate = ! $alternate;
    }

    return $sum % 10 === 0;
}

function storePath(): string
{
    return '/data/store.json';
}

function readStore(): array
{
    $path = storePath();
    if (! is_file($path)) {
        return [];
    }

    $handle = fopen($path, 'c+');
    if ($handle === false) {
        return [];
    }
    flock($handle, LOCK_SH);
    $raw = stream_get_contents($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    $decoded = json_decode($raw ?: '[]', true);

    return is_array($decoded) ? $decoded : [];
}

function writeStore(array $store): void
{
    $path = storePath();
    $directory = dirname($path);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $handle = fopen($path, 'c+');
    if ($handle === false) {
        respond(500, ['message' => 'Não foi possível abrir o arquivo do mock.']);
    }
    flock($handle, LOCK_EX);
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($store, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

function mockLog(string $message): void
{
    file_put_contents('php://stdout', $message . "\n");
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

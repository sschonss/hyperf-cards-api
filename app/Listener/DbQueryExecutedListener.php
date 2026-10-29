<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Collection\Arr;
use Hyperf\Database\Events\QueryExecuted;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function Hyperf\Support\env;

/**
 * Grava o SQL já com os bindings no canal sql (runtime/logs/sql.log).
 * Um valor com cara de PAN é mascarado: o log de query não pode virar trilha de cartão.
 */
#[Listener]
class DbQueryExecutedListener implements ListenerInterface
{
    private LoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(LoggerFactory::class)->get('sql', 'sql');
    }

    public function listen(): array
    {
        return [
            QueryExecuted::class,
        ];
    }

    public function process(object $event): void
    {
        if (! filter_var(env('DB_QUERY_LOG', false), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if (! $event instanceof QueryExecuted) {
            return;
        }

        $sql = $event->sql;
        if (! Arr::isAssoc($event->bindings)) {
            $position = 0;
            foreach ($event->bindings as $value) {
                $position = strpos($sql, '?', $position);
                if ($position === false) {
                    break;
                }
                $rendered = "'" . $this->redact($value) . "'";
                $sql = substr_replace($sql, $rendered, $position, 1);
                $position += strlen($rendered);
            }
        }

        $this->logger->info(sprintf('[%sms] %s', $event->time, $sql));
    }

    private function redact(mixed $value): string
    {
        $text = (string) $value;
        if (preg_match('/^\d{13,19}$/', $text) === 1) {
            return '[PAN-REDACTED]';
        }

        return $text;
    }
}

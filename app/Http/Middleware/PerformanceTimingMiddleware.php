<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Temporary request timing for production diagnostics.
 * Active only when the query string contains perf=1.
 *
 * db_ready: elapsed time from perf measurement start until the first DB
 * connection becomes available (ConnectionEstablished). This includes any
 * Laravel processing before the first DB access — not pure PDO connect time.
 */
class PerformanceTimingMiddleware
{
    private bool $listenersRegistered = false;

    private bool $measuring = false;

    private ?float $perfStartedAt = null;

    private ?float $dbReadyMs = null;

    private int $queryCount = 0;

    private float $queryTimeMs = 0.0;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldMeasure($request)) {
            /** @var Response $response */
            $response = $next($request);

            return $response;
        }

        $this->registerListenersOnce();
        $this->resetMetrics();
        $this->measuring = true;
        $this->perfStartedAt = microtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);

            $response->headers->set('Server-Timing', $this->buildServerTimingHeader());

            return $response;
        } finally {
            $this->measuring = false;
        }
    }

    private function shouldMeasure(Request $request): bool
    {
        return $request->query('perf') === '1';
    }

    private function registerListenersOnce(): void
    {
        if ($this->listenersRegistered) {
            return;
        }

        Event::listen(ConnectionEstablished::class, function (): void {
            if (! $this->measuring || $this->dbReadyMs !== null || $this->perfStartedAt === null) {
                return;
            }

            $this->dbReadyMs = (microtime(true) - $this->perfStartedAt) * 1000;
        });

        DB::listen(function ($query): void {
            if (! $this->measuring) {
                return;
            }

            $this->queryCount++;
            $this->queryTimeMs += (float) $query->time;
        });

        $this->listenersRegistered = true;
    }

    private function resetMetrics(): void
    {
        $this->dbReadyMs = null;
        $this->queryCount = 0;
        $this->queryTimeMs = 0.0;
    }

    private function buildServerTimingHeader(): string
    {
        $appStart = defined('LARAVEL_START') ? \LARAVEL_START : $this->perfStartedAt;

        $parts = [
            sprintf('app_total;dur=%.1f', (microtime(true) - $appStart) * 1000),
        ];

        if ($this->dbReadyMs !== null) {
            $parts[] = sprintf('db_ready;dur=%.1f', $this->dbReadyMs);
        }

        $parts[] = sprintf('db_queries;dur=%.1f', $this->queryTimeMs);
        $parts[] = sprintf('db_query_count;desc="%d"', $this->queryCount);

        return implode(', ', $parts);
    }
}

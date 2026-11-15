<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Middleware\Middleware;

/**
 * Rate limit middleware — fail-closed when storage fails (D5).
 *
 * Per-key throttling using an in-memory sliding window.
 * State is per-worker (limitation documented in SECURITY.md §7).
 */
final class RateLimiter implements Middleware
{
    private int $maxRequests;
    private int $windowSeconds;
    /** @var array<string, int[]> key => timestamps of requests within current window */
    private array $buckets = [];

    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(Request $req, callable $next): Response
    {
        $key = $this->resolveKey($req);

        try {
            if ($this->isExceeded($key)) {
                return Response::error(
                    'RATE_LIMITED',
                    'Too many requests. Try again later.',
                    429,
                    ['retry_after' => $this->windowSeconds],
                );
            }
            $this->record($key);
        } catch (\Throwable $e) {
            // Fail-closed: block the request rather than allowing it through
            return Response::error('INTERNAL_ERROR', 'Rate limit check failed.', 500);
        }

        $resp = $next($req);
        return $resp;
    }

    /**
     * Resolve the rate-limit key from the request.
     *
     * In a real implementation this could be IP, auth token, or a combination.
     * Default: client IP header or remote address.
     */
    private function resolveKey(Request $req): string
    {
        $ip = $req->header('X-Forwarded-For') ?? $req->queryParam('remote_addr') ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return (string)$ip;
    }

    private function isExceeded(string $key): bool
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;

        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [];
        }

        // Prune entries outside the window
        $filtered = array_values(array_filter($this->buckets[$key], fn (int $ts) => $ts > $windowStart));
        $this->buckets[$key] = $filtered;

        return count($filtered) >= $this->maxRequests;
    }

    private function record(string $key): void
    {
        $now = time();
        if (!isset($this->buckets[$key])) {
            $this->buckets[$key] = [];
        }
        $this->buckets[$key][] = $now;
    }

    /**
     * Reset a key's bucket (for testing).
     */
    public function reset(string $key): void
    {
        unset($this->buckets[$key]);
    }
}

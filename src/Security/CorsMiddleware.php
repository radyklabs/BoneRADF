<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Contracts\Middleware;

/**
 * CORS middleware — enables cross-origin requests from an allowlisted origin.
 *
 * The frontend SPA (sample_frontdev) is served from a different origin than
 * the API (boneradfw.local) and calls it via `fetch`. Without CORS headers
 * the browser blocks the response. This middleware sets `Access-Control-*`
 * headers and handles the OPTIONS preflight.
 *
 * Fail-closed posture: only the configured origin is allowed; everything else
 * gets the default (no CORS headers), so the browser enforces its own policy.
 */
final class CorsMiddleware implements Middleware
{
    /** @var string[]  allowed Origin header values */
    private array $allowedOrigins;

    /** @param string[] $allowedOrigins  e.g. ['http://localhost:4173', 'http://127.0.0.1:4173'] */
    public function __construct(array $allowedOrigins = [])
    {
        $this->allowedOrigins = $allowedOrigins;
    }

    public function handle(Request $req, callable $next): Response
    {
        $resp = $next($req);

        $origin = $req->header('Origin');
        if ($origin === null || !in_array($origin, $this->allowedOrigins, true)) {
            return $resp;
        }

        $resp = $resp
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-CSRF-Token')
            ->withHeader('Access-Control-Max-Age', '86400');

        // Preflight: short-circuit with 204 + the CORS headers above.
        if (strtoupper($req->method()) === 'OPTIONS') {
            return $resp->withHeader('Content-Length', '0');
        }

        return $resp;
    }

    /**
     * @return string[]
     */
    public function allowedOrigins(): array
    {
        return $this->allowedOrigins;
    }
}

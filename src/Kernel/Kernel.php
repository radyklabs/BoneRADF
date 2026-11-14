<?php

declare(strict_types=1);

namespace Boneradf\Kernel;

use Throwable;

/**
 * Framework kernel: boot pipeline and request lifecycle.
 *
 * Boot order:
 *   1. Autoloader (registered by the time this class is loaded)
 *   2. Config (planned P2 — not wired yet)
 *   3. Middleware pipeline (planned P1 — not wired yet)
 *   4. Router (planned P1 — not wired yet)
 *   5. Dispatch → handler → response
 *
 * Unhandled exceptions are caught and converted to a uniform JSON error
 * envelope. No stack traces in the response body.
 */
final class Kernel
{
    private const PRODUCTION = true;

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            // ponytail: static PRODUCTION flag; upgrade path — env-aware config (P2)
            $message = self::PRODUCTION ? 'Internal server error.' : $e->getMessage();
            return Response::error('INTERNAL_ERROR', $message, 500);
        }
    }

    private function dispatch(Request $request): Response
    {
        // ponytail: no router yet (P1) — every request falls through to 404.
        // Once Router lands, replace this with:
        //   $route = $this->router->match($request);
        //   if ($route === null) { return 404; }
        //   return ($route->handler)($request, $route->params);
        return Response::error('NOT_FOUND', 'Route not found.', 404);
    }
}

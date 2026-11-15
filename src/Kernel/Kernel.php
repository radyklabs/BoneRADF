<?php

declare(strict_types=1);

namespace Boneradf\Kernel;

use Boneradf\Router\Router;
use Boneradf\Middleware\Pipeline;
use Throwable;

/**
 * Framework kernel: boot pipeline and request lifecycle.
 *
 * Boot order:
 *   1. Autoloader (registered by the time this class is loaded)
 *   2. Router + Pipeline (set externally at boot)
 *   3. Dispatch → middleware → handler → response
 *
 * Unhandled exceptions are caught and converted to a uniform JSON error
 * envelope. No stack traces in the response body.
 */
final class Kernel
{
    private const PRODUCTION = true;

    private Router $router;
    private Pipeline $pipeline;

    public function __construct(?Router $router = null, ?Pipeline $pipeline = null)
    {
        $this->router   = $router ?? new Router();
        $this->pipeline = $pipeline ?? new Pipeline();
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function pipeline(): Pipeline
    {
        return $this->pipeline;
    }

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
        $match = $this->router->match($request);

        if ($match === null) {
            // Distinguish 404 (path unknown) from 405 (path known, wrong method)
            if ($this->router->pathExists($request->path())) {
                return Response::error('METHOD_NOT_ALLOWED', 'Method not allowed for this path.', 405);
            }
            return Response::error('NOT_FOUND', 'Route not found.', 404);
        }

        /** @var \Boneradf\Router\Route $route */
        $route  = $match['route'];
        $params = $match['params'];

        // Resolve route-specific middleware into the pipeline
        $pipeline = $this->pipeline;
        foreach ($route->middleware() as $name) {
            $mw = $this->router->getMiddleware($name);
            if ($mw !== null) {
                $pipeline->add($mw);
            }
        }

        $endpoint = fn (Request $req): Response => $route->invoke($req, $params);

        return $pipeline->run($request, $endpoint);
    }
}

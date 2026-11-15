<?php

declare(strict_types=1);

namespace Boneradf\Kernel;

use Boneradf\Router\Router;
use Boneradf\Middleware\Pipeline;
use Boneradf\Container\Container;
use Boneradf\Config\Config;
use Boneradf\Logger\Logger;
use Boneradf\Logger\FileLogger;
use Throwable;

/**
 * Framework kernel: boot pipeline and request lifecycle.
 *
 * Boot order:
 *   1. Autoloader
 *   2. Config (optional — defaults if not provided)
 *   3. Container (explicit bindings)
 *   4. Logger
 *   5. Router + Middleware Pipeline
 *   6. Dispatch → handler → response
 *
 * Unhandled exceptions → uniform JSON error envelope. No stack traces.
 */
final class Kernel
{
    private const PRODUCTION = true;

    private Router $router;
    private Pipeline $pipeline;
    private ?Container $container;
    private ?Config $config;
    private ?Logger $logger;

    public function __construct(
        ?Router $router = null,
        ?Pipeline $pipeline = null,
        ?Container $container = null,
        ?Config $config = null,
        ?Logger $logger = null,
    ) {
        $this->router    = $router ?? new Router();
        $this->pipeline  = $pipeline ?? new Pipeline();
        $this->container = $container;
        $this->config    = $config;
        $this->logger    = $logger;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function pipeline(): Pipeline
    {
        return $this->pipeline;
    }

    public function container(): ?Container
    {
        return $this->container;
    }

    public function config(): ?Config
    {
        return $this->config;
    }

    public function logger(): ?Logger
    {
        return $this->logger;
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->dispatch($request);
        } catch (Throwable $e) {
            $this->logger?->error('unhandled_exception', [
                'exception' => $e->getMessage(),
                'path'      => $request->path(),
            ]);

            $message = self::PRODUCTION ? 'Internal server error.' : $e->getMessage();
            return Response::error('INTERNAL_ERROR', $message, 500);
        }
    }

    private function dispatch(Request $request): Response
    {
        $match = $this->router->match($request);

        if ($match === null) {
            if ($this->router->pathExists($request->path())) {
                return Response::error('METHOD_NOT_ALLOWED', 'Method not allowed for this path.', 405);
            }
            return Response::error('NOT_FOUND', 'Route not found.', 404);
        }

        /** @var \Boneradf\Router\Route $route */
        $route  = $match['route'];
        $params = $match['params'];

        // Resolve route-specific middleware
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

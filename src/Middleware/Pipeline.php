<?php

declare(strict_types=1);

namespace Boneradf\Middleware;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;

/**
 * Ordered middleware pipeline.
 *
 * Registration order = execution order on the way in; reverse on the way out.
 * Each middleware's handle() receives the request and a `next` callable.
 * Returning without calling `next` short-circuits the pipeline.
 * Exceptions thrown by any stage bubble to the kernel error handler.
 */
final class Pipeline
{
    /** @var Middleware[] */
    private array $middlewares = [];

    public function add(Middleware $mw): void
    {
        $this->middlewares[] = $mw;
    }

    /**
     * @return Middleware[]
     */
    public function all(): array
    {
        return $this->middlewares;
    }

    /**
     * Run the pipeline and return the final response.
     *
     * @param Request  $req
     * @param callable $endpoint  fn(Request): Response — the innermost handler
     * @return Response
     */
    public function run(Request $req, callable $endpoint): Response
    {
        // Build the chain from innermost to outermost.
        $handler = $endpoint;
        for ($i = count($this->middlewares) - 1; $i >= 0; $i--) {
            $mw = $this->middlewares[$i];
            $next = $handler;
            $handler = fn (Request $r): Response => $mw->handle($r, $next);
        }
        return $handler($req);
    }
}

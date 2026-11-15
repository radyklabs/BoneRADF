<?php

declare(strict_types=1);

namespace Boneradf\Middleware;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;

/**
 * PSR-15-style middleware interface.
 *
 * Zero-dependency: PSR-shaped by convention (D2), not formal PSR-15 compliance.
 */
interface Middleware
{
    /**
     * Process the request through this middleware.
     *
     * @param Request  $req
     * @param callable $next  fn(Request): Response — advances the pipeline
     * @return Response
     */
    public function handle(Request $req, callable $next): Response;
}

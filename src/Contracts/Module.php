<?php

declare(strict_types=1);

namespace Boneradf\Contracts;

use Boneradf\Container\Container;
use Boneradf\Middleware\Pipeline;
use Boneradf\Router\Router;

/**
 * A BoneRADF module (ARCHITECTURE §5).
 *
 * A module is a PSR-4 package directory under `modules/` that self-registers
 * via this single interface. It may bind services into the container (explicit
 * bindings only), register routes, and register middleware onto the pipeline.
 *
 * A module must NOT: write to globals, emit output during boot, or mutate
 * another module's bindings.
 */
interface Module
{
    /**
     * Unique module name (used for registry + `bone modules`).
     */
    public function name (): string;

    /**
     * Called at boot, after core security middleware is registered.
     *
     * @param Container $container  explicit DI bindings
     * @param Router    $router     register routes
     * @param Pipeline  $pipeline   register middleware
     */
    public function boot (Container $container, Router $router, Pipeline $pipeline): void;
}

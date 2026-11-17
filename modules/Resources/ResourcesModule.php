<?php

declare(strict_types=1);

namespace Modules\Resources;

use Boneradf\Container\Container;
use Boneradf\Database\Database;
use Boneradf\Middleware\Pipeline;
use Boneradf\Contracts\Module;
use Boneradf\Router\Router;
use Modules\Resources\Controllers\ResourceController;
use Modules\Resources\Models\ResourceModel;
use Modules\Resources\Services\ResourceService;

/**
 * Resources module — CRUD over the sample `resources` table.
 *
 * Routes are declared in Config/routing.php; this class only wires the
 * container bindings. Public URLs:
 *   GET    /api/resources        → list
 *   POST   /api/resources        → create { name }
 *   GET    /api/resources/{id}   → read
 *   PUT    /api/resources/{id}   → update { name }
 *   DELETE /api/resources/{id}   → delete
 */
final class ResourcesModule implements Module
{
    public function name(): string
    {
        return 'resources';
    }

    public function boot(Container $container, Router $router, Pipeline $pipeline): void
    {
        $service = new ResourceService(new ResourceModel(Database::fromEnv()));
        $container->instance('resources.service', $service);
        $container->instance(ResourceController::class, new ResourceController($service));
    }
}

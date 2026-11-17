<?php

declare(strict_types=1);

namespace Modules\Resources;

use Boneradf\Container\Container;
use Boneradf\Database\ConnectionFactory;
use Boneradf\Middleware\Pipeline;
use Boneradf\Contracts\Module;
use Boneradf\Router\Router;
use PDO;

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
        $dsn    = getenv('BONERADF_DB_DSN') ?: 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
        $dbUser = getenv('BONERADF_DB_USER') ?: 'root';
        $dbPass = getenv('BONERADF_DB_PASS') ?: 'root';

        $pdo = (new ConnectionFactory($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES  => false,
        ]))->connect();

        $service = new ResourceService($pdo);
        $container->instance('resources.service', $service);
        $container->instance(ResourceController::class, new ResourceController($service));
    }
}

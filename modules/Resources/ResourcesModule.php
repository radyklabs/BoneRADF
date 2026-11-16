<?php

declare(strict_types=1);

namespace Modules\Resources;

use Boneradf\Container\Container;
use Boneradf\Database\ConnectionFactory;
use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Middleware\Pipeline;
use Boneradf\Module\Module;
use Boneradf\Router\Router;
use PDO;

/**
 * Resources module — CRUD over the sample `resources` table.
 *
 * Public URLs (module prefix `/resources` under the default `/api`):
 *   GET    /api/resources       → list
 *   POST   /api/resources       → create  { name }
 *   GET    /api/resources/:id   → read
 *   PUT    /api/resources/:id   → update  { name }
 *   DELETE /api/resources/:id   → delete
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

        $router->group('/resources', function (Router $r) use ($service): void {
            // The SPA calls the collection as `/api/resources` (no trailing
            // slash), so the group pattern is `''` (empty) as well as `'/'`.
            // ponytail: the router has no trailing-slash normalization; when a
            // canonical-URL policy lands, drop the empty-pattern duplicate.
            $list = fn (): Response => Response::json(
                array_map([ResourceService::class, 'toResource'], $service->list())
            );
            $r->get('',  $list);
            $r->get('/', $list);

            // POST → create
            $create = function (Request $req) use ($service): Response {
                [$ok, $row, $errors] = $service->create($req->body());
                if ($errors !== null) {
                    return Response::error('VALIDATION_FAILED', 'Validation failed.', 422, ['errors' => $errors]);
                }
                if ($ok === false) {
                    return Response::error('INTERNAL_ERROR', 'Could not create resource.', 500);
                }
                return Response::json(ResourceService::toResource($row), 201);
            };
            $r->post('',  $create);
            $r->post('/', $create);

            // GET /:id → read
            $r->get('/:id', function (Request $req, array $params) use ($service): Response {
                if (!ResourceService::validId($params['id'])) {
                    return Response::error('VALIDATION_FAILED', 'Invalid resource id.', 422);
                }
                $row = $service->find($params['id']);
                if ($row === null) {
                    return Response::error('NOT_FOUND', 'Resource not found.', 404);
                }
                return Response::json(ResourceService::toResource($row));
            });

            // PUT /:id → update
            $r->put('/:id', function (Request $req, array $params) use ($service): Response {
                if (!ResourceService::validId($params['id'])) {
                    return Response::error('VALIDATION_FAILED', 'Invalid resource id.', 422);
                }
                [$ok, $row, $errors] = $service->update($params['id'], $req->body());
                if ($errors !== null) {
                    return Response::error('VALIDATION_FAILED', 'Validation failed.', 422, ['errors' => $errors]);
                }
                if ($ok === false || $row === null) {
                    return Response::error('NOT_FOUND', 'Resource not found.', 404);
                }
                return Response::json(ResourceService::toResource($row));
            });

            // DELETE /:id → delete
            $r->delete('/:id', function (Request $req, array $params) use ($service): Response {
                if (!ResourceService::validId($params['id'])) {
                    return Response::error('VALIDATION_FAILED', 'Invalid resource id.', 422);
                }
                if (!$service->delete($params['id'])) {
                    return Response::error('NOT_FOUND', 'Resource not found.', 404);
                }
                return Response::noContent(204);
            });

            // Preflight (OPTIONS) routes: the global CORS middleware attaches
            // the headers; the handler returns 204.
            $preflight = fn (): Response => Response::noContent(204);
            $r->add('OPTIONS', '',    $preflight);
            $r->add('OPTIONS', '/',   $preflight);
            $r->add('OPTIONS', '/:id', $preflight);
        });
    }
}

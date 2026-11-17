<?php declare (strict_types=1);

namespace Modules\Resources\Controllers;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Modules\Resources\Services\ResourceService;

/**
 * Resources HTTP handlers. Bound to the container by ResourcesModule::boot();
 * resolved per-request by the route handler closure in Boot.
 */
final class ResourceController
{
    public function __construct (
        private readonly ResourceService $service
    ) {}

    public function list (Request $req, array $params): Response
    {
        return Response::json (
            array_map ([ResourceService::class, 'toResource'], $this->service->list ())
        );
    }

    public function show (Request $req, array $params): Response
    {
        if (!ResourceService::validId ($params['id'])) {
            return Response::error ('VALIDATION_FAILED', 'Invalid resource id.', 422);
        }
        $row = $this->service->find ($params['id']);
        if ($row === null) {
            return Response::error ('NOT_FOUND', 'Resource not found.', 404);
        }
        return Response::json (ResourceService::toResource ($row));
    }

    public function create (Request $req, array $params): Response
    {
        [$ok, $row, $errors] = $this->service->create ($req->body ());
        if ($errors !== null) {
            return Response::error ('VALIDATION_FAILED', 'Validation failed.', 422, ['errors' => $errors]);
        }
        if ($ok === false) {
            return Response::error ('INTERNAL_ERROR', 'Could not create resource.', 500);
        }
        return Response::json (ResourceService::toResource ($row), 201);
    }

    public function update (Request $req, array $params): Response
    {
        if (!ResourceService::validId ($params['id'])) {
            return Response::error ('VALIDATION_FAILED', 'Invalid resource id.', 422);
        }
        [$ok, $row, $errors] = $this->service->update ($params['id'], $req->body ());
        if ($errors !== null) {
            return Response::error ('VALIDATION_FAILED', 'Validation failed.', 422, ['errors' => $errors]);
        }
        if ($ok === false || $row === null) {
            return Response::error ('NOT_FOUND', 'Resource not found.', 404);
        }
        return Response::json (ResourceService::toResource ($row));
    }

    public function delete (Request $req, array $params): Response
    {
        if (!ResourceService::validId ($params['id'])) {
            return Response::error ('VALIDATION_FAILED', 'Invalid resource id.', 422);
        }
        if (!$this->service->delete ($params['id'])) {
            return Response::error ('NOT_FOUND', 'Resource not found.', 404);
        }
        return Response::noContent (204);
    }

    public function preflight (Request $req, array $params): Response
    {
        return Response::noContent (204);
    }
}

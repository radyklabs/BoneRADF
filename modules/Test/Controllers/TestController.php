<?php declare (strict_types=1);

namespace Modules\Test\Controllers;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
// use Modules\Resources\Services\ResourceService;

/**
 * Resources HTTP handlers. Bound to the container by ResourcesModule::boot();
 * resolved per-request by the route handler closure in Boot.
 */
final class TestController
{
    public function __construct (
        // private readonly ResourceService $service
    ) {}

    public function index (Request $req, array $params): Response
    {
        return Response::json (
            ['status' => 'This is test']
        );
    }
}

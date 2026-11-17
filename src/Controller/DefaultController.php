<?php declare (strict_types=1);

namespace Boneradf\Controller;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;

/**
 * System-level default handlers (src/Manifest/Routing.php).
 */
final class DefaultController
{
    public function index (Request $req, array $params): Response
    {
        // return Response::json ([
        //     'success' => ['code' => 200, 'message' => 'Welcome to PHP Headless Framework by RADLabs Studio!'],
        // ]);
        return Response::view('landing');
    }

    public function health (Request $req, array $params): Response
    {
        return Response::json (['status' => 'ok']);
    }
}

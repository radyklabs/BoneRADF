<?php declare (strict_types=1);

namespace Modules\Resources;

use Modules\Resources\Controllers\ResourceController;

/**
 * Resources module route contributions.
 *
 * @return array<string, array{0: class-string, 1: string}>
 */
return [
    'GET    /api/resources'        => [ResourceController::class, 'list'],
    'GET    /api/resources/{id:int}' => [ResourceController::class, 'show'],
    'POST   /api/resources'        => [ResourceController::class, 'create'],
    'PUT    /api/resources/{id:int}' => [ResourceController::class, 'update'],
    'DELETE /api/resources/{id:int}' => [ResourceController::class, 'delete'],
    'OPTIONS /api/resources'       => [ResourceController::class, 'preflight'],
    'OPTIONS /api/resources/{id:int}' => [ResourceController::class, 'preflight'],
];

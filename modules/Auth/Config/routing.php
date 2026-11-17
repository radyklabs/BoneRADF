<?php declare (strict_types=1);

namespace Modules\Auth;

/**
 * Auth module route contributions.
 *
 * Additive to src/Manifest/Routing.php; merged by Boot. A module not listed
 * in src/Manifest/Modules.php cannot register routes — this file is inert
 * unless the module is active.
 *
 * @return array<string, array{0: class-string, 1: string}>
 */
return [
    'POST /api/auth/login'      => [AuthController::class, 'login'],
    'POST /api/auth/refresh'    => [AuthController::class, 'refresh'],
    'GET  /api/csrf-token'      => [AuthController::class, 'csrfToken'],
    'OPTIONS /api/auth/login'   => [AuthController::class, 'preflight'],
    'OPTIONS /api/auth/refresh' => [AuthController::class, 'preflight'],
];

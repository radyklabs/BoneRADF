<?php declare (strict_types=1);

namespace Modules\Auth;

use Modules\Auth\Controllers\AuthController;

/**
 * Auth module route contributions.
 *
 * Additive to src/Manifest/Routing.php; merged by Boot. This file is also
 * the module's activation switch: an empty array keeps the module inert
 * (auto-discovered but never booted).
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

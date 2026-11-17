<?php

declare(strict_types=1);

namespace Modules\Auth;

use Boneradf\Config\Config;
use Boneradf\Container\Container;
use Boneradf\Database\Database;
use Boneradf\Middleware\Pipeline;
use Boneradf\Contracts\Module;
use Boneradf\Router\Router;
use Boneradf\Security\AuthGuard;
use Boneradf\Security\CorsMiddleware;
use Modules\Auth\Controllers\AuthController;
use Modules\Auth\Models\UserModel;
use Modules\Auth\Services\LoginService;

/**
 * Auth module — exposes `/api/auth/login` + `/api/auth/refresh` over the
 * `users` table, issuing JWT access + refresh pairs (D10: HS256, 1h + 7d).
 *
 * This is the development-layer sample for the framework's module contract
 * (ARCHITECTURE §5). It self-registers routes + the global CORS middleware in
 * `boot()`; the composition root (`public/index.php`) is the only place that
 * instantiates it. Route aliases now live in Config/routing.php (declarative,
 * additive); boot() only wires the container.
 */
final class AuthModule implements Module
{
    public function name(): string
    {
        return 'auth';
    }

    public function boot(Container $container, Router $router, Pipeline $pipeline): void
    {
        $config = Config::loadSystem ();
        $secret = $config->get ('boneradf.app_key');

        $auth = new AuthGuard($secret, 3600, 604800);
        $container->instance('auth.guard', $auth);

        $login = new LoginService(new UserModel(Database::fromEnv()), $auth);
        $container->instance('auth.login', $login);
        $container->instance(AuthController::class, new AuthController($login));

        $cors = new CorsMiddleware($this->corsOrigins ($config));
        $pipeline->add($cors);
    }

    /**
     * @return string[] allowed CORS origins.
     */
    private function corsOrigins (Config $config): array
    {
        $defaults = [
            'http://localhost:4173',
            'http://127.0.0.1:4173',
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'http://boneradfw.local',
            'http://faceradfw.local',
            'http://localhost',
            'http://127.0.0.1',
        ];
        $env = $config->getOr ('boneradf.cors', '');
        if ($env !== false && $env !== '')
        {
            return array_values(array_filter(array_map('trim', explode(',', $env))));
        }
        return $defaults;
    }
}

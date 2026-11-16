<?php

declare(strict_types=1);

namespace Modules\Auth;

use Boneradf\Container\Container;
use Boneradf\Database\ConnectionFactory;
use Boneradf\Kernel\Response;
use Boneradf\Middleware\Pipeline;
use Boneradf\Module\Module;
use Boneradf\Router\Router;
use Boneradf\Security\AuthGuard;
use Boneradf\Security\CorsMiddleware;
use Boneradf\Kernel\Request;
use Boneradf\Validator\Validator;
use PDO;

/**
 * Auth module — exposes `/api/auth/login` + `/api/auth/refresh` over the
 * `users` table, issuing JWT access + refresh pairs (D10: HS256, 1h + 7d).
 *
 * This is the development-layer sample for the framework's module contract
 * (ARCHITECTURE §5). It self-registers routes + the global CORS middleware in
 * `boot()`; the composition root (`public/index.php`) is the only place that
 * instantiates it.
 */
final class AuthModule implements Module
{
    public function name(): string
    {
        return 'auth';
    }

    public function boot(Container $container, Router $router, Pipeline $pipeline): void
    {
        // Resolve config for the DB + JWT secret. Values come from env vars
        // with dev-only fallbacks matching docs/CONFIG.md (DB `local` @ 10028).
        // ponytail: no .env loader yet (P2.1) — getenv fallbacks only.
        $secret = getenv('BONERADF_APP_KEY') ?: 'dev-only-insecure-jwt-secret';
        $dsn    = getenv('BONERADF_DB_DSN') ?: 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
        $dbUser = getenv('BONERADF_DB_USER') ?: 'root';
        $dbPass = getenv('BONERADF_DB_PASS') ?: 'root';

        $auth = new AuthGuard($secret, 3600, 604800);
        $container->instance('auth.guard', $auth);

        $pdo = (new ConnectionFactory($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES  => false,
        ]))->connect();
        $container->instance('db', $pdo);

        $login = new LoginService($pdo, $auth);
        $container->instance('auth.login', $login);

        // CORS is global (runs on every response) so the SPA can call this
        // module's endpoints cross-origin. Origin allowlist from env or the
        // dev default set.
        $cors = new CorsMiddleware($this->corsOrigins());
        $pipeline->add($cors);

        $this->registerRoutes($router, $login);
    }

    private function registerRoutes(Router $router, LoginService $login): void
    {
        // POST /api/auth/login — { username, password } → { access, refresh, user }
        $router->post('/api/auth/login', function (Request $req) use ($login): Response {
            $body     = $req->body();
            $username = (string)($body['username'] ?? '');
            $password = (string)($body['password'] ?? '');

            $result = (new Validator(['username' => $username, 'password' => $password]))->validate([
                'username' => 'required|string|min:1|max:60',
                'password' => 'required|string|min:1|max:255',
            ]);
            if ($result->fails()) {
                return Response::error('VALIDATION_FAILED', 'Validation failed.', 422, ['errors' => $result->errors()]);
            }

            [$ok, $tokens] = $login->attempt($username, $password);
            if (!$ok) {
                return Response::error('INVALID_CREDENTIALS', 'Invalid credentials.', 401);
            }

            return Response::json([
                'access'  => $tokens['access'],
                'refresh' => $tokens['refresh'],
                'user'    => $tokens['user'],
            ]);
        });

        // POST /api/auth/refresh — { refreshToken } → { access }.
        // Intentionally no rotation: the same refresh token is re-usable until
        // it expires (D10 7d). Rotate when logout/revocation lands.
        $router->post('/api/auth/refresh', function (Request $req) use ($login): Response {
            $refreshToken = (string)($req->body()['refreshToken'] ?? '');
            $newAccess    = $login->refresh($refreshToken);
            if ($newAccess === null) {
                return Response::error('INVALID_REFRESH_TOKEN', 'Invalid or expired refresh token.', 401);
            }
            return Response::json(['access' => $newAccess]);
        });

        // Preflight (OPTIONS) routes: the router has no wildcard; without these
        // a bare OPTIONS would 405. The global CORS middleware attaches the
        // headers; the handler just returns 204.
        $preflight = fn (): Response => Response::noContent(204);
        $router->add('OPTIONS', '/api/auth/login',   $preflight);
        $router->add('OPTIONS', '/api/auth/refresh', $preflight);
    }

    /**
     * @return string[] allowed CORS origins.
     */
    private function corsOrigins(): array
    {
        $defaults = [
            'http://localhost:4173',
            'http://127.0.0.1:4173',
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'http://boneradfw.local',
            'http://localhost',
            'http://127.0.0.1',
        ];
        $env = getenv('BONERADF_CORS_ORIGINS');
        if ($env !== false && $env !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $env))));
        }
        return $defaults;
    }
}

<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Contracts\Middleware;

/**
 * RBAC middleware — deny-by-default (D5).
 *
 * Route declares required permission, e.g. `middleware('rbac:users.create')`.
 * The permission is checked against the authenticated user's roles.
 *
 * No permission declared → deny. Missing config → deny.
 */
final class RbacGuard implements Middleware
{
    /** @var array<string, string[]>  role => [permissions] */
    private array $roleMap;
    private ?string $requiredPermission;

    /**
     * @param array<string, string[]> $roleMap  role => [permissions]
     * @param string|null             $required Permission required for this route
     */
    public function __construct (array $roleMap, ?string $required = null)
    {
        $this->roleMap = $roleMap;
        $this->requiredPermission = $required;
    }

    public function handle (Request $req, callable $next): Response
    {
        // Public routes (no permission declared) pass through. This is the
        // D5 "ON by default for protected routes" contract: a route that
        // does NOT declare an `rbac:*` middleware is public; a route that
        // DOES declare one is protected and deny-by-default (403 / 401).
        if ($this->requiredPermission === null)
        {
            return $next ($req);
        }

        $user = $req->user ();
        if ($user === null)
        {
            return Response::error ('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $role = (string) ($user['role'] ?? '');
        $permissions = $this->roleMap[$role] ?? [];

        if (!in_array ($this->requiredPermission, $permissions, true))
        {
            return Response::error ('FORBIDDEN', 'Insufficient permissions.', 403);
        }

        return $next ($req);
    }

    /**
     * Build an RbacGuard from a route middleware name (`rbac:users.create`).
     * The module registers it via `$router->registerMiddleware('rbac:…', …)`,
     * then a route declares `middleware: ['rbac:users.create']`.
     */
    public static function fromMiddlewareName (string $name, array $roleMap): self
    {
        // name format: rbac:users.create
        $perm = str_starts_with ($name, 'rbac:') ? substr ($name, 5) : null;
        return new self ($roleMap, $perm);
    }
}

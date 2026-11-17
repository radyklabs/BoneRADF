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
    public function __construct(array $roleMap, ?string $required = null)
    {
        $this->roleMap = $roleMap;
        $this->requiredPermission = $required;
    }

    public function handle(Request $req, callable $next): Response
    {
        if ($this->requiredPermission === null) {
            return Response::error('FORBIDDEN', 'No permission declared for this route.', 403);
        }

        $user = $req->user();
        if ($user === null) {
            return Response::error('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $role = (string)($user['role'] ?? '');
        $permissions = $this->roleMap[$role] ?? [];

        if (!in_array($this->requiredPermission, $permissions, true)) {
            return Response::error('FORBIDDEN', 'Insufficient permissions.', 403);
        }

        return $next($req);
    }

    public static function fromMiddlewareName(string $name, array $roleMap): self
    {
        // name format: rbac:users.create
        $perm = str_starts_with($name, 'rbac:') ? substr($name, 5) : null;
        return new self($roleMap, $perm);
    }
}

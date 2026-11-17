<?php declare (strict_types=1);

namespace Modules\Auth;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Validator\ValidationResult;

/**
 * Auth HTTP handlers. Bound to the container by AuthModule::boot().
 */
final class AuthController
{
    public function __construct (
        private readonly LoginService $login
    ) {}

    public function login (Request $req, array $params): Response
    {
        $body     = $req->body ();
        $username = (string)($body['username'] ?? '');
        $password = (string)($body['password'] ?? '');

        $result = (new \Boneradf\Validator\Validator (['username' => $username, 'password' => $password]))->validate ([
            'username' => 'required|string|min:1|max:60',
            'password' => 'required|string|min:1|max:255',
        ]);
        if ($result->fails ()) {
            return Response::error ('VALIDATION_FAILED', 'Validation failed.', 422, ['errors' => $result->errors ()]);
        }

        [$ok, $tokens] = $this->login->attempt ($username, $password);
        if (!$ok) {
            return Response::error ('INVALID_CREDENTIALS', 'Invalid credentials.', 401);
        }

        return Response::json ([
            'access'  => $tokens['access'],
            'refresh' => $tokens['refresh'],
            'user'    => $tokens['user'],
        ]);
    }

    public function refresh (Request $req, array $params): Response
    {
        $refreshToken = (string)($req->body ()['refreshToken'] ?? '');
        $newAccess    = $this->login->refresh ($refreshToken);
        if ($newAccess === null) {
            return Response::error ('INVALID_REFRESH_TOKEN', 'Invalid or expired refresh token.', 401);
        }
        return Response::json (['access' => $newAccess]);
    }

    /**
     * Placeholder CSRF token — the SPA boot request needs CORS headers, which
     * the global middleware attaches. Static empty token until signed cookies
     * ship (ponytail).
     */
    public function csrfToken (Request $req, array $params): Response
    {
        return Response::json (['token' => '']);
    }

    public function preflight (Request $req, array $params): Response
    {
        return Response::noContent (204);
    }
}

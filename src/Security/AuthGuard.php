<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Middleware\Middleware;

/**
 * JWT HS256 auth middleware — fail-closed when unconfigured (D5).
 *
 * Reads `Authorization: Bearer <token>`, verifies signature + exp + nbf,
 * and attaches the decoded payload to the request via an attribute array.
 *
 * Unauthenticated request → 401 UNAUTHENTICATED.
 */
final class AuthGuard implements Middleware
{
    private string $secret;
    private string $algo = 'HS256';
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct(
        string $secret,
        int $accessTtl = 3600,
        int $refreshTtl = 604800,
    ) {
        $this->secret = $secret;
        $this->accessTtl = $accessTtl;
        $this->refreshTtl = $refreshTtl;
    }

    public function handle(Request $req, callable $next): Response
    {
        $auth = $req->header('Authorization');
        if ($auth === null || !str_starts_with($auth, 'Bearer ')) {
            return Response::error('UNAUTHENTICATED', 'Missing or malformed Authorization header.', 401);
        }

        $token = substr($auth, 7);
        $payload = $this->verify($token);
        if ($payload === null) {
            return Response::error('UNAUTHENTICATED', 'Invalid or expired token.', 401);
        }

        // Attach identity to request (mutable clone)
        $req2 = $req->withUser($payload);
        return $next($req2);
    }

    /**
     * Issue a signed JWT.
     *
     * @param array<string, mixed> $claims  e.g. ['sub' => 42, 'role' => 'admin']
     */
    public function issue(array $claims): string
    {
        $header = ['alg' => $this->algo, 'typ' => 'JWT'];
        $now = time();
        $claims = array_merge([
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
        ], $claims);

        $h = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $p = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $sig = hash_hmac('sha256', $h . '.' . $p, $this->secret, true);
        return $h . '.' . $p . '.' . $this->base64UrlEncode($sig);
    }

    /**
     * Verify and decode a JWT. Returns claims or null.
     *
     * @return array<string, mixed>|null
     */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $sig] = $parts;

        $expected = hash_hmac('sha256', $h . '.' . $p, $this->secret, true);
        $expectedB64 = $this->base64UrlEncode($expected);
        if (!hash_equals($expectedB64, $sig)) {
            return null;
        }

        // Algorithm must match (no negotiation from token header)
        $header = json_decode($this->base64UrlDecode($h), true);
        if (!is_array($header) || ($header['alg'] ?? '') !== $this->algo) {
            return null;
        }

        $claims = json_decode($this->base64UrlDecode($p), true);
        if (!is_array($claims)) {
            return null;
        }

        // exp / nbf
        if (isset($claims['exp']) && time() > (int)$claims['exp']) {
            return null;
        }
        if (isset($claims['nbf']) && time() < (int)$claims['nbf']) {
            return null;
        }

        return $claims;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}

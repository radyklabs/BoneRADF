<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Middleware\Middleware;

/**
 * CSRF middleware — ON by default for cookie-authenticated flows (D5).
 *
 * State-changing methods (POST/PUT/PATCH/DELETE) require a valid
 * X-CSRF-Token header. GET/HEAD are exempt.
 *
 * Failure → 403 CSRF_TOKEN_INVALID.
 */
final class CsrfGuard implements Middleware
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    public function handle(Request $req, callable $next): Response
    {
        $stateChanging = in_array($req->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if (!$stateChanging) {
            return $next($req);
        }

        $token = $req->header('X-CSRF-Token');
        if ($token === null || !$this->verify($token)) {
            return Response::error('CSRF_TOKEN_INVALID', 'Missing or invalid CSRF token.', 403);
        }

        return $next($req);
    }

    /**
     * Issue a signed CSRF token.
     */
    public function issue(int $ttl = 3600): string
    {
        $payload = base64_encode(random_bytes(16) . '|' . (time() + $ttl));
        $sig = hash_hmac('sha256', $payload, $this->secret);
        return $payload . '.' . $sig;
    }

    private function verify(string $token): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return false;
        }
        [$payload, $sig] = $parts;
        $expected = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expected, $sig)) {
            return false;
        }
        // Check expiry
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return false;
        }
        $pipePos = strrpos($decoded, '|');
        if ($pipePos === false) {
            return false;
        }
        $exp = (int)substr($decoded, $pipePos + 1);
        return $exp >= time();
    }

    /**
     * @return string
     */
    private function random_bytes(int $n): string
    {
        return random_bytes($n);
    }
}

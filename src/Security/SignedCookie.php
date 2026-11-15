<?php

declare(strict_types=1);

namespace Boneradf\Security;

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;

/**
 * Signed cookie helper — HMAC-SHA256 signed values.
 *
 * Cookie format: `value.hmac_sha256(value, secret)`
 * Verification uses `hash_equals` (constant-time compare).
 */
final class SignedCookie
{
    private string $secret;

    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * Create a signed cookie value.
     */
    public function sign(string $value): string
    {
        $sig = hash_hmac('sha256', $value, $this->secret);
        return $value . '.' . $sig;
    }

    /**
     * Verify and return the value, or null if invalid.
     */
    public function verify(string $signed): ?string
    {
        $pos = strrpos($signed, '.');
        if ($pos === false || $pos === 0 || $pos === strlen($signed) - 1) {
            return null;
        }
        $value = substr($signed, 0, $pos);
        $sig = substr($signed, $pos + 1);
        $expected = hash_hmac('sha256', $value, $this->secret);
        return hash_equals($expected, $sig) ? $value : null;
    }

    /**
     * Issue a session cookie value.
     */
    public function issueSession(string $id): string
    {
        return $this->sign($id);
    }

    /**
     * Verify a session cookie value.
     */
    public function verifySession(string $cookie): ?string
    {
        return $this->verify($cookie);
    }
}

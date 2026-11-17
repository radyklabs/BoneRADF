<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use Boneradf\Security\AuthGuard;
use Boneradf\Validator\Validator;
use Modules\Auth\Models\UserModel;
use PDOException;

/**
 * Login service — validates credentials against the `users` table and issues
 * an access + refresh JWT pair.
 *
 * UserModel owns the `users` query; this service holds the auth business
 * rules. Fail-closed: DB errors surface as a failed login, never as a
 * successful one.
 *
 * ponytail: the refresh token is a separate JWT with a longer TTL (D10: access
 * 1h + refresh 7d). A refresh *revocation* list is deferred — a refresh token
 * is only re-validated by signature + exp, not by a DB lookup. Upgrade when a
 * logout / token-revocation requirement lands.
 */
final class LoginService
{
    private UserModel $users;
    private AuthGuard $auth;

    public function __construct(UserModel $users, AuthGuard $auth)
    {
        $this->users = $users;
        $this->auth  = $auth;
    }

    /**
     * Authenticate a username/password pair.
     *
     * @return array{0: bool, 1: array<string,mixed>|null}  [ok, tokens]
     *         tokens = ['access'=>string, 'refresh'=>string, 'user'=>['id'=>string,'username'=>string]]
     */
    public function attempt(string $username, string $password): array
    {
        // Strict input validation at the trust boundary.
        $result = (new Validator([
            'username' => $username,
            'password' => $password,
        ]))->validate([
            'username' => 'required|string|min:1|max:60',
            'password' => 'required|string|min:1|max:255',
        ]);
        if ($result->fails()) {
            return [false, null];
        }

        try {
            $row = $this->users->findByUsername($username);
        } catch (PDOException) {
            // DB failure — fail-closed. Do not leak the error message.
            return [false, null];
        }

        // A stable dummy hash so failed lookups take the same time as real
        // checks (avoid a user-existence oracle via response timing).
        $dummy = self::dummyHash();
        if ($row === null) {
            password_verify($password, $dummy);
            return [false, null];
        }

        if (!is_string($row['password_hash']) || !password_verify($password, $row['password_hash'])) {
            return [false, null];
        }

        $claims = [
            'sub'    => (string)(int)$row['id'],
            'username' => (string)$row['username'],
            'role'   => (string)($row['role'] ?? 'user'),
            'jti'    => bin2hex(random_bytes(8)),
        ];

        $access  = $this->auth->issue($claims);

        // Refresh token: same claims, longer expiry (D10: 7d).
        $refresh = $this->auth->issueRefresh($claims);

        return [
            true,
            [
                'access'  => $access,
                'refresh' => $refresh,
                'user'    => [
                    'id'       => (string)(int)$row['id'],
                    'username' => (string)$row['username'],
                ],
            ],
        ];
    }

    /**
     * Exchange a refresh token for a new access token.
     *
     * @return string|null  new access token, or null if the refresh token is
     *                      invalid / expired / not a refresh token.
     */
    public function refresh(string $refreshToken): ?string
    {
        $claims = $this->auth->verify($refreshToken);
        if ($claims === null) {
            return null;
        }
        // Only accept tokens issued as refresh tokens (type claim set by issueRefresh).
        if (($claims['type'] ?? null) !== 'refresh') {
            return null;
        }
        // Rebuild access claims from the verified refresh claims (drop the type marker).
        $accessClaims = $claims;
        unset($accessClaims['type']);
        return $this->auth->issue($accessClaims);
    }

    /**
     * A process-wide dummy hash (generated on first use) so failed lookups
     * spend the same time as real checks — no user-existence oracle.
     */
    private static string $dummyHash = '';

    public static function dummyHash(): string
    {
        if (self::$dummyHash === '') {
            self::$dummyHash = password_hash('timing-equalizer', PASSWORD_DEFAULT);
        }
        return self::$dummyHash;
    }
}

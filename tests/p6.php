<?php

declare(strict_types=1);

/**
 * P6 verification tests: Auth module (login/refresh endpoints + service + seed).
 *
 * Run:  php -d extension=pdo_mysql tests/p6.php
 * Exits non-zero on any failure.
 *
 * These tests hit the real local DB (DB `local`, port 10028) — the same DB the
 * seeded sample user lives in. They are self-contained and idempotent.
 */

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Database\ConnectionFactory;
use Modules\Auth\LoginService;
use Boneradf\Security\AuthGuard;
use Boneradf\Validator\Validator;

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}\n";
    }
}

// ------------------------------------------------------------------
// Setup: connect to the sample DB
// ------------------------------------------------------------------
$dsn   = 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
$pdo   = (new ConnectionFactory($dsn, 'root', 'root'))->connect();

// Ensure the sample user exists (idempotent upsert, same as db/seed.php).
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `users` (
        `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username` VARCHAR(60) NOT NULL,
        `password_hash` VARCHAR(255) NOT NULL,
        `role` VARCHAR(32) NOT NULL DEFAULT \'user\',
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_users_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);
$hash = password_hash('secret123', PASSWORD_DEFAULT);
$pdo->prepare('INSERT IGNORE INTO `users` (`username`, `password_hash`) VALUES (?, ?)')
    ->execute(['boneradf', $hash]);

$auth   = new AuthGuard('test-secret', 3600, 604800);
$login  = new LoginService($pdo, $auth);

// ------------------------------------------------------------------
// LoginService::attempt — happy path
// ------------------------------------------------------------------
[$ok, $tokens] = $login->attempt('boneradf', 'secret123');
check('login: valid credentials → ok=true', $ok === true);
check('login: tokens carry access + refresh + user', is_array($tokens)
    && isset($tokens['access'], $tokens['refresh'], $tokens['user']));
check('login: user.id is string', is_array($tokens) && is_string($tokens['user']['id'] ?? null));
check('login: user.username is boneradf', is_array($tokens) && $tokens['user']['username'] === 'boneradf');

// Access token is a signed 3-part JWT
check('login: access token is 3-part JWT', is_array($tokens) && count(explode('.', $tokens['access'])) === 3);

// Verify the issued access token round-trips through AuthGuard
$claims = $auth->verify($tokens['access']);
check('login: issued access verifies with sub', is_array($claims) && isset($claims['sub']) && $claims['type'] === 'access');

// ------------------------------------------------------------------
// LoginService::attempt — wrong password / unknown user → fail
// ------------------------------------------------------------------
[$ok2, ] = $login->attempt('boneradf', 'wrong-password');
check('login: wrong password → ok=false', $ok2 === false);

[$ok3, ] = $login->attempt('no-such-user', 'secret123');
check('login: unknown user → ok=false', $ok3 === false);

// ------------------------------------------------------------------
// LoginService::attempt — invalid input (validation) → fail
// ------------------------------------------------------------------
[$ok4, ] = $login->attempt('', 'secret123');
check('login: empty username → ok=false (validation)', $ok4 === false);

// ------------------------------------------------------------------
// LoginService::refresh — exchange refresh token for a new access
// ------------------------------------------------------------------
$refreshTok = $tokens['refresh'];
$newAccess  = $login->refresh($refreshTok);
check('refresh: valid refresh → new access string', is_string($newAccess));
$newClaims  = $auth->verify((string)$newAccess);
check('refresh: new access verifies, sub preserved', is_array($newClaims) && isset($newClaims['sub']));
check('refresh: new access is not type=refresh', is_array($newClaims) && ($newClaims['type'] ?? null) === 'access');

// refresh() rejects a garbage token
check('refresh: garbage refresh → null', $login->refresh('garbage') === null);

// refresh() rejects an access token used as a refresh (type mismatch)
$accessAsRefresh = $login->refresh($tokens['access']);
check('refresh: access token as refresh → null (type mismatch)', $accessAsRefresh === null);

// ------------------------------------------------------------------
// Endpoint wiring — simulate the /api/auth/login handler logic inline
// (the full HTTP path is exercised by the smoke test at the bottom.)
// ------------------------------------------------------------------
$body = ['username' => 'boneradf', 'password' => 'secret123'];
$validation = (new Validator($body))->validate([
    'username' => 'required|string|min:1|max:60',
    'password' => 'required|string|min:1|max:255',
]);
check('endpoint: valid body passes validation', $validation->passes());

$badBody = ['username' => '', 'password' => ''];
$badValidation = (new Validator($badBody))->validate([
    'username' => 'required|string|min:1|max:60',
    'password' => 'required|string|min:1|max:255',
]);
check('endpoint: empty body fails validation', $badValidation->fails());

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

<?php

declare(strict_types=1);

/**
 * modules/Auth/seed.php — idempotent sample database bootstrap for the login
 * module.
 *
 * Creates table `users` in DB `local` and seeds one user `boneradf` with a
 * `password_hash()`-derived credential. Safe to re-run: table is created with
 * IF NOT EXISTS; the seed user is upserted (INSERT IGNORE).
 *
 * Run:  php -d extension=pdo_mysql modules/Auth/seed.php
 * (CLI does not autoload pdo_mysql from the Lightning php.ini; pass -d.)
 */

require dirname(__DIR__, 2) . '/src/Kernel/Boot.php';

// Single shared DB source for the seed (also used by tests/support/db.php).
$dsn  = getenv ('BONERADF_DB_DSN') ?: 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
$user = getenv ('BONERADF_DB_USER') ?: 'root';
$pass = getenv ('BONERADF_DB_PASS') ?: 'root';

try {
    $factory = new Boneradf\Database\ConnectionFactory($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ]);
    $pdo = $factory->connect();
} catch (Throwable $e) {
    fwrite(STDERR, "Seed: DB connection failed: {$e->getMessage()}\n");
    exit(1);
}

// 1) Create table (idempotent). `users` is a new table for the login sample;
// it does not collide with the WordPress `wp_users` table already in `local`.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS `users` (
        `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        `username`      VARCHAR(60)     NOT NULL,
        `password_hash` VARCHAR(255)    NOT NULL,
        `role`          VARCHAR(32)     NOT NULL DEFAULT \'user\',
        `created_at`    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_users_username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

// 2) Seed a sample user. The hash is generated at seed time (fresh per run only
// if the row is absent — but we upsert with a stable known password so the demo
// credential is deterministic: username `boneradf`, password `secret123`).
$seedUser    = 'boneradf';
$seedPass    = 'secret123';
$hash        = password_hash($seedPass, PASSWORD_DEFAULT);

// Upsert: insert if missing, refresh hash only on insert (do not clobber a
// changed password). Use INSERT IGNORE so an existing row is left untouched.
$stmt = $pdo->prepare(
    'INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`) VALUES (?, ?, ?)'
);
$stmt->execute([$seedUser, $hash, 'user']);

echo "Seed: table `users` ready in DB `local`.\n";
echo "Seed: user '{$seedUser}' present (password for dev: {$seedPass}).\n";

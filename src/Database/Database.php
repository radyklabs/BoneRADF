<?php declare (strict_types=1);

namespace Boneradf\Database;

use PDO;

/**
 * System-level PDO source — the single place that reads BONERADF_DB_* env.
 *
 * `fromEnv()` memoizes the connection per process: every module calling it
 * shares one PDO (no per-module duplicate connections). Module boot wires
 * `new XModel(Database::fromEnv())`; nothing else reads DB env settings.
 *
 * ponytail: one DSN per process. Upgrade path when a second database lands
 * (read replica / per-module SQLite): named connections `Database::get('name')`.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Shared PDO built from env with framework-default fallbacks.
     *
     * @throws \RuntimeException on connection failure
     */
    public static function fromEnv (): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $dsn  = getenv ('BONERADF_DB_DSN') ?: 'mysql:host=127.0.0.1;port=10028;dbname=local;charset=utf8mb4';
        $user = getenv ('BONERADF_DB_USER') ?: 'root';
        $pass = getenv ('BONERADF_DB_PASS') ?: 'root';

        return self::$pdo = (new ConnectionFactory ($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]))->connect ();
    }
}

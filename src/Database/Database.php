<?php declare (strict_types=1);

namespace Boneradf\Database;

use Boneradf\Config\Config;
use PDO;
use RuntimeException;

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
     * Shared PDO built from `Config` (system-level `BONERADF_DB_*`).
     *
     * Fail-closed: throws when a required DB config key is unset or empty —
     * no hardcoded dev DSN/user/pass fallbacks (the LocalWP `root/root`
     * values are gone; see decision D9 "secrets always from env").
     *
     * @throws RuntimeException when a required key is missing
     * @throws \PDOException     on connection failure
     */
    public static function fromEnv (): PDO
    {
        if (self::$pdo !== null)
        {
            return self::$pdo;
        }

        $config = Config::loadSystem ();
        $dsn    = $config->get ('boneradf.db.dsn');
        $user   = $config->getOr ('boneradf.db.user', '');
        $pass   = $config->getOr ('boneradf.db.pass', '');

        if ($dsn === '' || $dsn === null)
        {
            throw new RuntimeException (
                'Database: BONERADF_DB_DSN is not set. Provide it in the environment (or a Config).'
            );
        }

        return self::$pdo = (new ConnectionFactory ($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]))->connect ();
    }
}

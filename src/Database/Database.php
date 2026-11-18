<?php declare (strict_types=1);

namespace Boneradf\Database;

use Boneradf\Config\Config;
use PDO;

/**
 * System-level PDO source — the single place that reads DB settings.
 *
 * `fromEnv()` memoizes one connection per process: every module calling it
 * shares one PDO (no per-module duplicate connections). Module boot wires
 * `new XModel(Database::fromEnv())`; nothing else reads DB settings.
 *
 * Connection selection is a runtime choice (`Config::connectionName()`):
 *   database.<name>.{driver,host,port,name,charset,user,pass} + database.env
 * Multiple named connections (default, test, …) can coexist; adding one is
 * a `.env` block + no code change.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /**
     * Shared PDO for the runtime-selected connection.
     *
     * Fail-closed: throws when the selected connection's DSN cannot be
     * built (missing host/name, unsupported driver). No hardcoded DSN
     * fallbacks (D9).
     *
     * @throws RuntimeException when the connection config is missing/invalid
     * @throws \PDOException     on connection failure
     */
    public static function fromEnv (): PDO
    {
        if (self::$pdo !== null)
        {
            return self::$pdo;
        }

        $config = Config::loadSystem ();
        $dsn    = $config->dsn ();
        $user   = $config->dbUser ();
        $pass   = $config->dbPass ();

        return self::$pdo = (new ConnectionFactory ($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]))->connect ();
    }
}

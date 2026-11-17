<?php

declare(strict_types=1);

namespace Boneradf\Config;

use RuntimeException;

/**
 * Typed config with env > file > defaults precedence.
 *
 * Keys have declared types; a type mismatch fails at access time with a
 * clear error. Missing required keys fail. Missing optional keys return
 * their default.
 */
final class Config
{
    /** @var array<string, mixed> */
    private array $data;

    /** @var array<string, string> declared types per key */
    private array $types;

    /** @var array<string, mixed> defaults */
    private array $defaults;

    /** @var array<string, bool> required flags */
    private array $required;

    /**
     * Process-wide singleton. `Boot::init()` builds it via
     * `Config::loadSystem()`; every other consumer (Database, AuthGuard,
     * CsrfGuard, modules) reads through it — no more inline `getenv()`.
     *
     * ponytail: one Config per process (the kernel is per-request but the
     * env it was built from is not). Upgrade path: a per-environment
     * override if a deploy ever needs runtime config reload.
     */
    private static ?self $instance = null;

    /** @var array<string, string> connection name => DSN */
    private array $connections = [];

    /**
     * Build the process-wide system Config from the dev `.env` file and the
     * real environment.
     *
     * Dot-notated keys (see .env):
     *   app.key                                   required app secret
     *   database.<name>.{driver,host,port,name,charset,user,pass}
     *   database.env                              selects <name> at runtime
     *
     * Real env always wins over the file. Named connections beyond
     * `default`/`test` can be added by dropping extra `database.<name>.*`
     * lines in `.env` — no code change.
     *
     * @throws RuntimeException when app.key or the selected DB connection is missing.
     */
    public static function loadSystem (): self
    {
        if (self::$instance !== null)
        {
            return self::$instance;
        }

        $env = [];
        foreach (self::parseDotEnv (self::dotEnvFile ()) as $k => $v)
        {
            $env[$k] = $v;
        }

        self::$instance = new self (
            self::defaults (),
            self::types (),
            ['app.key' => true],
            [],           // file layer (reserved; .env lands in $env via parseDotEnv)
            array_merge (self::realEnv (), $env),
        );

        // Empty-string values are "unset" for our fail-closed keys.
        foreach (['app.key'] as $k)
        {
            if (self::$instance->get ($k) === '')
            {
                throw new RuntimeException ("Config: required key '{$k}' must not be empty.");
            }
        }

        // Pre-build DSNs for the documented named connections.
        self::$instance->registerConnection ('default');
        self::$instance->registerConnection ('test');

        return self::$instance;
    }

    /** @return array<string, mixed> */
    private static function defaults (): array
    {
        return [
            'app.key'                 => null,
            'database.default.driver' => 'mysql',
            'database.default.host'   => 'localhost',
            'database.default.port'   => '3306',
            'database.default.name'   => '',
            'database.default.charset'=> 'utf8mb4',
            'database.default.user'   => 'root',
            'database.default.pass'   => '',
            'database.test.driver'    => 'mysql',
            'database.test.host'      => '127.0.0.1',
            'database.test.port'      => '10028',
            'database.test.name'      => 'local',
            'database.test.charset'   => 'utf8mb4',
            'database.test.user'      => 'root',
            'database.test.pass'      => '',
            'database.env'            => 'default',
            'cors.origins'            => '',
        ];
    }

    /** @return array<string, string> */
    private static function types (): array
    {
        $out = [
            'app.key'              => 'string',
            'database.env'         => 'string',
            'cors.origins'         => 'string',
        ];
        foreach (['default', 'test'] as $n)
        {
            $out["database.{$n}.driver"]  = 'string';
            $out["database.{$n}.host"]    = 'string';
            $out["database.{$n}.port"]    = 'int';
            $out["database.{$n}.name"]    = 'string';
            $out["database.{$n}.charset"] = 'string';
            $out["database.{$n}.user"]    = 'string';
            $out["database.{$n}.pass"]    = 'string';
        }
        return $out;
    }

    private static function dotEnvFile (): ?string
    {
        $dir = dirname (__DIR__, 2);
        $file = $dir . '/.env';
        return is_readable ($file) ? $file : null;
    }

    /**
     * Real environment overrides for the dot-notated keys. Multiple alias
     * spellings per key (flat + dotted, upper-case). First non-empty wins.
     *
     * @return array<string, string>
     */
    private static function realEnv (): array
    {
        $map = [
            'app.key'        => ['APP_KEY', 'BONERADF_APP_KEY'],
            'database.env'   => ['BONERADF_DB_ENV', 'DATABASE_ENV'],
            'cors.origins'   => ['BONERADF_CORS_ORIGINS', 'CORS_ORIGINS'],
        ];
        foreach (['default', 'test'] as $n)
        {
            $U = strtoupper ($n);
            $map["database.{$n}.driver"]  = ["DATABASE_{$U}_DRIVER"];
            $map["database.{$n}.host"]    = ["DATABASE_{$U}_HOST"];
            $map["database.{$n}.port"]    = ["DATABASE_{$U}_PORT"];
            $map["database.{$n}.name"]    = ["DATABASE_{$U}_NAME"];
            $map["database.{$n}.charset"] = ["DATABASE_{$U}_CHARSET"];
            $map["database.{$n}.user"]    = ["DATABASE_{$U}_USER"];
            $map["database.{$n}.pass"]    = ["DATABASE_{$U}_PASS"];
        }

        $out = [];
        foreach ($map as $key => $candidates)
        {
            foreach ($candidates as $cand)
            {
                $v = getenv ($cand);
                if ($v !== false && $v !== '')
                {
                    $out[$key] = $v;
                    break;
                }
            }
        }
        return $out;
    }

    /** @return array<string, string> */
    private static function parseDotEnv (?string $file): array
    {
        $out = [];
        if ($file === null)
        {
            return $out;
        }
        $lines = file ($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false)
        {
            return $out;
        }
        foreach ($lines as $line)
        {
            $line = trim ($line);
            if ($line === '' || str_starts_with ($line, '#'))
            {
                continue;
            }
            $eq = strpos ($line, '=');
            if ($eq === false)
            {
                continue;
            }
            $k = trim (substr ($line, 0, $eq));
            $v = trim (substr ($line, $eq + 1));
            if ($k !== '')
            {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /**
     * Register + compile a named connection's DSN into memory. Extra
     * `database.<name>.*` connections need no code change — call this for
     * any name present in config.
     */
    private static function registerConnection (string $name): void
    {
        $config = self::$instance;
        $driver = $config->getOr ("database.{$name}.driver", null);
        if ($driver === null)
        {
            return; // connection not declared anywhere → skip.
        }

        $dsn = self::buildDsn ($name);
        self::$instance->connections["database.{$name}"] = $dsn;
    }

    private static function buildDsn (string $name): string
    {
        $c      = self::$instance;
        $driver = $c->get ("database.{$name}.driver");
        $host   = $c->get ("database.{$name}.host");
        $port   = $c->get ("database.{$name}.port");
        $db     = $c->get ("database.{$name}.name");
        $cs     = $c->get ("database.{$name}.charset");

        if ($db === '')
        {
            throw new RuntimeException ("Config: database.{$name}.name must not be empty.");
        }

        switch ($driver)
        {
            case 'mysql':
                return "mysql:host={$host};port={$port};dbname={$db};charset={$cs}";
            case 'sqlite':
                // Resolve relative paths against project root so a .env
                // entry like `database.test.name=../logs/test.sqlite` works
                // from any CWD.
                $abs = $db;
                if ($db !== '' && !preg_match ('~^[a-zA-Z]:[\\\\/]~', $db)
                     && $db[0] !== '/' && $db[0] !== '\\')
                {
                    $root = dirname (__DIR__, 2); // src/Config -> app/
                    $abs = realpath ($root . '/..' . $db) ?: ($root . '/' . $db);
                }
                return "sqlite:{$abs}";
            default:
                throw new RuntimeException ("Config: unsupported driver '{$driver}' for database.{$name}.");
        }
    }

    /** The runtime `<name>` (`BONERADF_DB_ENV` env / `database.env`). */
    public function connectionName (): string
    {
        $env = getenv ('BONERADF_DB_ENV');
        if ($env !== false && $env !== '')
        {
            return $env;
        }
        return $this->getOr ('database.env', 'default') ?: 'default';
    }

    /** DSN for the runtime-selected connection. */
    public function dsn (): string
    {
        $name = $this->connectionName ();
        if (!isset ($this->connections["database.{$name}"]))
        {
            self::registerConnection ($name); // non-default name → build lazily.
        }
        if (!isset ($this->connections["database.{$name}"]))
        {
            throw new RuntimeException ("Config: database connection '{$name}' is not declared.");
        }
        return $this->connections["database.{$name}"];
    }

    public function dbUser (string $name = ''): string
    {
        $n = $name !== '' ? $name : $this->connectionName ();
        return $this->getOr ("database.{$n}.user", '');
    }

    public function dbPass (string $name = ''): string
    {
        $n = $name !== '' ? $name : $this->connectionName ();
        return $this->getOr ("database.{$n}.pass", '');
    }

    /**
     * Test/CLI escape hatch: replace the process-wide instance.
     */
    public static function setInstance (self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * @param array<string, mixed> $defaults  key => default value
     * @param array<string, string> $types    key => 'string'|'int'|'float'|'bool'|'array'
     * @param array<string, bool>  $required  key => true
     * @param array<string, string> $file     key => value (from .env or config file)
     * @param array<string, string> $env      key => value (from environment variables)
     */
    public function __construct (
        array $defaults = [],
        array $types = [],
        array $required = [],
        array $file = [],
        array $env = [],
    ) {
        $this->defaults = $defaults;
        $this->types    = $types;
        $this->required = $required;

        // Merge: env overrides file overrides defaults
        $this->data = array_merge ($this->defaults, $file, $env);

        // Validate required keys are present
        foreach ($this->required as $key => $isReq) 
        {
            if ($isReq && !array_key_exists ($key, $this->data))
            {
                throw new RuntimeException ("Config: required key '{$key}' is missing.");
            }
        }

        // Type-coerce declared values
        foreach ($this->types as $key => $type)
        {
            if (array_key_exists ($key, $this->data)) {
                $this->data[$key] = $this->coerce ($key, $this->data[$key], $type);
            }
        }
    }

    /**
     * Get a config value.
     *
     * @throws RuntimeException if key is missing and has no default
     */
    public function get (string $key): mixed
    {
        if (!array_key_exists ($key, $this->data))
        {
            throw new RuntimeException ("Config: key '{$key}' is not set.");
        }
        return $this->data[$key];
    }

    /**
     * Get a config value with a fallback default.
     */
    public function getOr (string $key, mixed $default): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Check if a key is set.
     */
    public function has (string $key): bool
    {
        return array_key_exists ($key, $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    public function all (): array
    {
        return $this->data;
    }

    private function coerce (string $key, mixed $value, string $type): mixed
    {
        switch ($type)
        {
            case 'string':
                if (!is_string ($value))
                {
                    throw new RuntimeException ("Config: key '{$key}' expects string, got " . gettype ($value));
                }
                return $value;

            case 'int':
                if (is_string ($value))
                {
                    $int = filter_var ($value, FILTER_VALIDATE_INT);
                    if ($int === false)
                    {
                        throw new RuntimeException ("Config: key '{$key}' expects int, got '{$value}'");
                    }

                    return $int;
                }

                if (!is_int ($value))
                {
                    throw new RuntimeException ("Config: key '{$key}' expects int, got " . gettype($value));
                }

                return $value;

            case 'float':
                if (is_string ($value))
                {
                    $float = filter_var ($value, FILTER_VALIDATE_FLOAT);
                    if ($float === false)
                    {
                        throw new RuntimeException ("Config: key '{$key}' expects float, got '{$value}'");
                    }

                    return $float;
                }

                if (!is_float ($value) && !is_int ($value))
                {
                    throw new RuntimeException ("Config: key '{$key}' expects float, got " . gettype($value));
                }
                return (float) $value;

            case 'bool':
                if (is_string($value))
                {
                    $lower = strtolower ($value);
                    if (in_array ($lower, ['true', '1', 'yes', 'on'], true))
                    {
                        return true;
                    }

                    if (in_array ($lower, ['false', '0', 'no', 'off'], true))
                    {
                        return false;
                    }

                    throw new RuntimeException ("Config: key '{$key}' expects bool, got '{$value}'");
                }

                if (!is_bool ($value))
                {
                    throw new RuntimeException ("Config: key '{$key}' expects bool, got " . gettype ($value));
                }

                return $value;

            case 'array':
                if (!is_array ($value))
                {
                    throw new RuntimeException ("Config: key '{$key}' expects array, got " . gettype ($value));
                }

                return $value;

            default:
                throw new RuntimeException ("Config: unknown type '{$type}' for key '{$key}'");
        }
    }
}

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

    /**
     * Build the process-wide system Config from BONERADF_* env vars.
     * Idempotent — repeated calls return the same instance.
     *
     * @throws RuntimeException if a required key (BONERADF_APP_KEY) is unset.
     */
    public static function loadSystem (): self
    {
        if (self::$instance !== null)
        {
            return self::$instance;
        }

        self::$instance = new self ([
            'boneradf.app_key'   => null,
            'boneradf.db.dsn'    => null,
            'boneradf.db.user'   => null,
            'boneradf.db.pass'   => null,
            'boneradf.cors'      => null,
        ], [
            'boneradf.app_key'   => 'string',
            'boneradf.db.dsn'    => 'string',
            'boneradf.db.user'   => 'string',
            'boneradf.db.pass'   => 'string',
            'boneradf.cors'      => 'string',
        ], [
            'boneradf.app_key'   => true,
            'boneradf.db.dsn'    => true,
            'boneradf.db.user'   => true,
            'boneradf.db.pass'   => true,
        ], [], [
            'boneradf.app_key'   => getenv ('BONERADF_APP_KEY') ?: '',
            'boneradf.db.dsn'    => getenv ('BONERADF_DB_DSN') ?: '',
            'boneradf.db.user'   => getenv ('BONERADF_DB_USER') ?: '',
            'boneradf.db.pass'   => getenv ('BONERADF_DB_PASS') ?: '',
            'boneradf.cors'      => getenv ('BONERADF_CORS_ORIGINS') ?: '',
        ]);

        // Empty-string env values mean "unset" for our fail-closed keys:
        // a caller passing a required key as '' would otherwise pass the
        // "array_key_exists" check above. Coerce '' → null and re-validate.
        foreach (['boneradf.app_key', 'boneradf.db.dsn', 'boneradf.db.user', 'boneradf.db.pass'] as $k)
        {
            if (self::$instance->get ($k) === '')
            {
                throw new RuntimeException ("Config: required key '{$k}' is set to an empty value; provide a real value.");
            }
        }

        return self::$instance;
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

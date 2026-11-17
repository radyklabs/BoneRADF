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

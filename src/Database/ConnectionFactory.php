<?php

declare(strict_types=1);

namespace Boneradf\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * PDO connection factory.
 *
 * Creates PDO instances from a DSN string. Connection options are set
 * once at factory time; the returned PDO is immutable to the caller.
 */
final class ConnectionFactory
{
    private string $dsn;
    private string $user;
    private string $pass;

    /** @var array<int, string> PDO::ATTR_* options */
    private array $options;

    /**
     * @param array<int, mixed> $options PDO constants (e.g. [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])
     */
    public function __construct (string $dsn, string $user = '', string $pass = '', array $options = [])
    {
        $this->dsn = $dsn;
        $this->user = $user;
        $this->pass = $pass;
        $this->options = $options ?: [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    /**
     * Create a new PDO connection.
     *
     * @throws RuntimeException on connection failure
     */
    public function connect (): PDO
    {
        try
        {
            return new PDO ($this->dsn, $this->user, $this->pass, $this->options);
        }
        catch (PDOException $e)
        {
            throw new RuntimeException ('DB connection failed: ' . $e->getMessage (), (int) $e->getCode (), $e);
        }
    }

    public function dsn (): string
    {
        return $this->dsn;
    }
}

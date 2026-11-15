<?php

declare(strict_types=1);

namespace Boneradf\Cache;

/**
 * Cache facade — delegates to a configured driver.
 */
final class Cache
{
    private CacheDriver $driver;

    public function __construct(CacheDriver $driver)
    {
        $this->driver = $driver;
    }

    public function get(string $key): mixed
    {
        return $this->driver->get($key);
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $this->driver->set($key, $value, $ttlSeconds);
    }

    public function delete(string $key): void
    {
        $this->driver->delete($key);
    }

    public function clear(): void
    {
        $this->driver->clear();
    }

    public function driver(): CacheDriver
    {
        return $this->driver;
    }
}

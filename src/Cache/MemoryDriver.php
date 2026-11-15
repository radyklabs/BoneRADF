<?php

declare(strict_types=1);

namespace Boneradf\Cache;

/**
 * In-memory cache driver.
 *
 * Per-request lifetime (one FPM worker = one service instance).
 * Suitable for request-scoped caching; not shared across workers.
 */
final class MemoryDriver implements CacheDriver
{
    /** @var array<string, array{value: mixed, expires: int}> */
    private array $store = [];

    public function get(string $key): mixed
    {
        if (!isset($this->store[$key])) {
            return null;
        }
        $entry = $this->store[$key];
        if ($entry['expires'] !== 0 && time() > $entry['expires']) {
            unset($this->store[$key]);
            return null;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $expires = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $this->store[$key] = ['value' => $value, 'expires' => $expires];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function clear(): void
    {
        $this->store = [];
    }
}

<?php

declare(strict_types=1);

namespace Boneradf\Contracts;

/**
 * Cache driver interface.
 *
 * v1: in-memory + file only (D10). Redis deferred.
 */
interface CacheDriver
{
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttlSeconds = 0): void;
    public function delete(string $key): void;
    public function clear(): void;
}

<?php

declare(strict_types=1);

namespace Boneradf\Cache;

use Boneradf\Contracts\CacheDriver;
use RuntimeException;

/**
 * File-based cache driver.
 *
 * Stores each key as a PHP file under $basePath containing the value
 * and expiry timestamp. Suitable for shared state across FPM workers.
 */
final class FileDriver implements CacheDriver
{
    private string $basePath;

    public function __construct (string $basePath = '/tmp/boneradf-cache')
    {
        $this->basePath = $basePath;

        if (!is_dir ($this->basePath))
        {
            if (!@mkdir ($this->basePath, 0755, true))
            {
                throw new RuntimeException ("Cache: cannot create base path '{$this->basePath}'");
            }
        }
    }

    public function get (string $key): mixed
    {
        $file = $this->pathFor ($key);
        if (!is_file($file))
        {
            return null;
        }

        $raw = file_get_contents ($file);
        if ($raw === false)
        {
            return null;
        }

        $decoded = @unserialize ($raw);
        // ponytail: PHP serialize on own cache files (our data, not untrusted) —
        // upgrade path: JSON-encode with value + expiry wrapper if external
        // write access becomes a concern.
        if ($decoded === false || !is_array ($decoded) || !array_key_exists ('value', $decoded))
        {
            return null;
        }

        if (($decoded['expires'] ?? 0) !== 0 && time () > $decoded['expires'])
        {
            @unlink($file);
            return null;
        }

        return $decoded['value'];
    }

    public function set (string $key, mixed $value, int $ttlSeconds = 0): void
    {
        $expires = $ttlSeconds > 0 ? time () + $ttlSeconds : 0;
        $payload = serialize (['value' => $value, 'expires' => $expires]);
        $file = $this->pathFor ($key);
        if (file_put_contents ($file, $payload, LOCK_EX) === false)
        {
            throw new RuntimeException ("Cache: failed to write key '{$key}'");
        }
    }

    public function delete (string $key): void
    {
        $file = $this->pathFor ($key);
        if (is_file ($file))
        {
            @unlink ($file);
        }
    }

    public function clear (): void
    {
        foreach (glob ($this->basePath . '/*.php') ?: [] as $f)
        {
            @unlink($f);
        }
    }

    private function pathFor (string $key): string
    {
        // Hash the key to a filesystem-safe name
        return $this->basePath . '/' . hash ('sha256', $key) . '.php';
    }
}

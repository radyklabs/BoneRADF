<?php

declare(strict_types=1);

namespace Boneradf\Container;

use RuntimeException;

/**
 * Light DI container — explicit bindings only.
 *
 * No reflection autowiring (D2/D10). What is not bound is not resolvable.
 * Missing bindings throw a typed exception at resolve time.
 */
final class Container
{
    /** @var array<string, callable> factories */
    private array $bindings = [];

    /** @var array<string, object> resolved instances (request-scoped) */
    private array $instances = [];

    /** @var array<string, bool> resolution stack (circular detection) */
    private array $resolving = [];

    /**
     * Bind a factory closure for a given id.
     *
     * @param string   $id      Binding key (class name, interface, or arbitrary string)
     * @param callable $factory  fn(Container $c): object
     */
    public function bind(string $id, callable $factory): void
    {
        $this->bindings[$id] = $factory;
        unset($this->instances[$id]);
    }

    /**
     * Bind a concrete instance (shared / singleton within this request).
     *
     * @param string $id
     * @param object $instance
     */
    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
    }

    /**
     * Check if an id is bound (factory or instance).
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]);
    }

    /**
     * Resolve a binding.
     *
     * @throws RuntimeException if unbound or circular
     */
    public function get(string $id): object
    {
        // Already resolved — return cached instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // No factory and no instance → fail
        if (!isset($this->bindings[$id])) {
            throw new RuntimeException("Container: no binding for '{$id}'.");
        }

        // Circular detection
        if (isset($this->resolving[$id])) {
            throw new RuntimeException("Container: circular dependency detected for '{$id}'.");
        }

        $this->resolving[$id] = true;
        try {
            $instance = ($this->bindings[$id])($this);
            $this->instances[$id] = $instance;
            return $instance;
        } finally {
            unset($this->resolving[$id]);
        }
    }

    /**
     * List all bound ids (factories + instances).
     *
     * @return string[]
     */
    public function keys(): array
    {
        return array_unique(array_merge(array_keys($this->bindings), array_keys($this->instances)));
    }
}

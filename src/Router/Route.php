<?php

declare(strict_types=1);

namespace Boneradf\Router;

/**
 * A single registered route.
 */
final class Route
{
    /**
     * @param string      $method       HTTP method (GET, POST, …)
     * @param string      $pattern      Path pattern with `:param` placeholders
     * @param callable    $handler      fn(Request $req, array $params): Response|array
     * @param string[]    $middleware   Route-specific middleware names
     * @param string      $name         Optional route name
     */
    public function __construct(
        private readonly string $method,
        private readonly string $pattern,
        private $handler,
        private readonly array $middleware = [],
        private readonly string $name = '',
    ) {
        // deviation: $handler is untyped (callable not allowed as property type in PHP 8.1+)
        // — the constructor param is callable-typed, so this is safe at runtime.
    }

    public function method(): string
    {
        return $this->method;
    }

    public function pattern(): string
    {
        return $this->pattern;
    }

    /** @return string[] */
    public function middleware(): array
    {
        return $this->middleware;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Handler callable (used by group re-registration).
     */
    public function handlerForGroup(): callable
    {
        return $this->handler;
    }

    /**
     * Extract parameter names from the pattern.
     *
     * @return string[]
     */
    public function paramNames(): array
    {
        preg_match_all('/:(\w+)/', $this->pattern, $m);
        return $m[1];
    }

    /**
     * Match a concrete path against this route's pattern.
     *
     * @return array<string, string>|null  params, or null if no match
     */
    public function match(string $path): ?array
    {
        // deviation: use ~ as delimiter instead of / to avoid collision with path slashes
        $regex = '~^' . preg_replace('/:(\w+)/', '(?P<$1>[^/]+)', $this->pattern) . '$~';

        if (preg_match($regex, $path, $matches)) {
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }
        return null;
    }

    /**
     * Invoke the handler.
     *
     * @param object $request
     * @param array<string, string> $params
     * @return \Boneradf\Kernel\Response
     */
    public function invoke(object $request, array $params): \Boneradf\Kernel\Response
    {
        $result = ($this->handler)($request, $params);
        if ($result instanceof \Boneradf\Kernel\Response) {
            return $result;
        }
        return \Boneradf\Kernel\Response::json($result);
    }
}

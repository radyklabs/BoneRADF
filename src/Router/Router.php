<?php

declare(strict_types=1);

namespace Boneradf\Router;

use Boneradf\Kernel\Request;
// use Boneradf\Kernel\Response;
use Boneradf\Contracts\Middleware;

/**
 * HTTP router: method + path matching with `:param` syntax.
 *
 * Routes are registered once (at boot) and the table is read-only
 * during dispatch.
 */
final class Router
{
    /** Default URL prefix prepended to module group prefixes. */
    public const DEFAULT_URL_PREFIX = '/api';

    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Middleware> named middleware available to routes */
    private array $middlewareByName = [];

    /**
     * Register a middleware class under a name so routes can reference it.
     */
    public function registerMiddleware (string $name, Middleware $mw): void
    {
        $this->middlewareByName[$name] = $mw;
    }

    /**
     * Register a route.
     *
     * @param string   $method     HTTP method (upper)
     * @param string   $pattern    Path pattern, e.g. `/users/{id:int}`
     * @param callable $handler    fn(Request, array $params): Response|array
     * @param string[] $middleware Route-specific middleware names
     * @param string   $name       Route name (alias source string)
     */
    public function add (string $method, string $pattern, callable $handler, array $middleware = [], string $name = ''): Route
    {
        $route = new Route (strtoupper ($method), $pattern, $handler, $middleware, $name);
        $this->routes[] = $route;
        return $route;
    }

    public function get (string $pattern, callable $handler, array $middleware = []): Route
    {
        return $this->add ('GET', $pattern, $handler, $middleware);
    }

    public function post (string $pattern, callable $handler, array $middleware = []): Route
    {
        return $this->add ('POST', $pattern, $handler, $middleware);
    }

    public function put (string $pattern, callable $handler, array $middleware = []): Route
    {
        return $this->add ('PUT', $pattern, $handler, $middleware);
    }

    public function patch (string $pattern, callable $handler, array $middleware = []): Route
    {
        return $this->add ('PATCH', $pattern, $handler, $middleware);
    }

    public function delete (string $pattern, callable $handler, array $middleware = []): Route
    {
        return $this->add ('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * Register a route group with a shared prefix.
     *
     * The group prefix is prepended to a default URL prefix (`/api` by
     * default) so module routes are namespaced under the API segment:
     * `group('/auth', …)` registers `/api/auth/…`. A module that wants a
     * different (or no) URL prefix passes `$urlPrefix` explicitly —
     * `group('/health', …, '')` registers `/health/…`.
     *
     * @param string   $prefix      module-scoped prefix (e.g. `/auth`)
     * @param callable $fn          fn(Router $r) — registers routes inside
     * @param string   $urlPrefix   URL prefix prepended to `$prefix` (default `/api`)
     */
    public function group (string $prefix, callable $fn, string $urlPrefix = self::DEFAULT_URL_PREFIX): void
    {
        $inner = new self();
        $fn($inner);
        $fullPrefix = $urlPrefix . $prefix;
        foreach ($inner->routes as $route)
        {
            $prefixed = $fullPrefix . $route->pattern ();
            $this->routes[] = new Route(
                $route->method (),
                $prefixed,
                $route->handlerForGroup (),
                $route->middleware (),
                $route->name (),
            );
        }
        $this->middlewareByName = array_merge ($this->middlewareByName, $inner->middlewareByName);
    }

    /**
     * @return Route[]
     */
    public function routes (): array
    {
        return $this->routes;
    }

    /**
     * Find a matching route for the request.
     *
     * @return array{route: Route, params: array<string,string>}|null
     */
    public function match (Request $req): ?array
    {
        $method = strtoupper ($req->method ());
        $path = $req->path ();

        foreach ($this->routes as $route)
        {
            if (strtoupper ($route->method ()) !== $method)
            {
                continue;
            }

            $params = $route->match ($path);
            if ($params !== null)
            {
                return ['route' => $route, 'params' => $params];
            }
        }

        return null;
    }

    /**
     * Check if any route matches the path (regardless of method) — for 405 detection.
     */
    public function pathExists (string $path): bool
    {
        foreach ($this->routes as $route)
        {
            if ($route->match ($path) !== null)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * Get registered middleware by name.
     */
    public function getMiddleware (string $name): ?Middleware
    {
        return $this->middlewareByName[$name] ?? null;
    }
}

<?php

declare(strict_types=1);

namespace Boneradf\Router;

use Boneradf\Kernel\Response;
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
    public function __construct (
        private readonly string $method,
        private readonly string $pattern,
        private $handler,
        private readonly array $middleware = [],
        private readonly string $name = '',
    ) {
        // deviation: $handler is untyped (callable not allowed as property type in PHP 8.1+)
        // — the constructor param is callable-typed, so this is safe at runtime.
    }

    public function method (): string
    {
        return $this->method;
    }

    public function pattern (): string
    {
        return $this->pattern;
    }

    /** @return string[] */
    public function middleware (): array
    {
        return $this->middleware;
    }

    public function name (): string
    {
        return $this->name;
    }

    /**
     * Handler callable (used by group re-registration).
     */
    public function handlerForGroup (): callable
    {
        return $this->handler;
    }

    /**
     * Typed segment types: name => regex body.
     * Custom regex is allowed via `{name:/regex/}`.
     */
    private const PARAM_TYPES = [
        'int'  => '[0-9]+',
        'num'  => '[0-9]+(?:\.[0-9]+)?',
        'slug' => '[a-z0-9-]+',
        'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
        'any'  => '[^/]+',
    ];

    /**
     * Extract parameter names from the pattern.
     *
     * Supports both `{name}` / `{name:type}` and legacy `:name`.
     *
     * @return string[]
     */
    public function paramNames (): array
    {
        preg_match_all ('/\{(\w+)(?::[^}]+)?\}|:(\w+)/', $this->pattern, $m);
        return array_values (array_filter (array_merge ($m[1], $m[2])));
    }

    /**
     * Match a concrete path against this route's pattern.
     *
     * Placeholders:
     *   {name}          → any non-slash segment
     *   {name:type}     → typed segment (see PARAM_TYPES)
     *   {name:/regex/}  → custom regex (delimiter-agnostic wrapper `/…/`)
     *
     * Legacy `:name` syntax is still matched as untyped.
     *
     * @return array<string, string>|null  params, or null if no match
     */
    public function match (string $path): ?array
    {
        $regex = $this->compileRegex ();
        if ($regex === null)
        {
            return null;
        }

        if (preg_match ($regex, $path, $matches))
        {
            $params = [];
            foreach ($matches as $key => $value)
            {
                if (is_string ($key))
                {
                    $params[$key] = $value;
                }
            }
            return $params;
        }
        return null;
    }

    /**
     * Compile the pattern to a PCRE regex. Returns null for invalid patterns.
     *
     * @return string|null
     */
    private function compileRegex (): ?string
    {
        $pattern = $this->pattern;
        $names   = [];

        // Escape every literal segment (no placeholder) up front, so a
        // literal `.` in a route pattern matches only `.`, not any char.
        $pattern = $this->escapeLiterals ($pattern);

        // Custom regex: {name:/body/} — capture inner body (no nested slashes issue).
        $pattern = preg_replace_callback (
            '/\{(\w+):\/([^\/]+)\/\}/',
            function (array $m) use (&$names): string
            {
                $names[] = $m[1];
                return '(?P<' . $m[1] . '>' . $m[2] . ')';
            },
            $pattern
        ) ?? $pattern;

        // Typed + untyped: {name:type} or {name}
        $pattern = preg_replace_callback (
            '/\{(\w+)(?::(\w+))?\}/',
            function (array $m) use (&$names): string
            {
                $name = $m[1];
                $type = $m[2] ?? 'any';
                if (!isset (self::PARAM_TYPES[$type])) {
                    return '(?P<' . $name . '>[^/]+)';
                }
                $names[] = $name;
                return '(?P<' . $name . '>' . self::PARAM_TYPES[$type] . ')';
            },
            $pattern
        ) ?? $pattern;

        // Legacy colon syntax: :name
        $pattern = preg_replace_callback (
            '/:(\w+)/',
            function (array $m) use (&$names): string
            {
                $names[] = $m[1];
                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $pattern
        ) ?? $pattern;

        // Escape anything left that PCRE would treat as a metacharacter,
        // preserving the already-inserted (?P<...>…) groups. (Done up front
        // in escapeLiterals on the raw pattern — no-op here.)
        $regex = '~^' . $pattern . '$~';

        if (@preg_match ($regex, '') === false)
        {
            return null;
        }

        return $regex;
    }

    /**
     * preg_quote every literal segment of $pattern except the placeholder
     * segments (`{name:type}`, `{name}`, `:name`, `{name:/regex/}`), which
     * are kept intact so the callbacks below can turn them into safe named
     * groups. A literal `.` in a route pattern matches only `.`, not any
     * char.
     */
    private function escapeLiterals (string $pattern): string
    {
        $parts = preg_split ('/(\{[^}]+\}|:\w+)/', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($parts === false)
        {
            return preg_quote ($pattern, '~');
        }

        $out = '';
        $isPlaceholder = false;
        foreach ($parts as $part)
        {
            $out .= $isPlaceholder ? $part : preg_quote ($part, '~');
            $isPlaceholder = !$isPlaceholder;
        }
        return $out;
    }

    /**
     * Invoke the handler.
     *
     * @param object $request
     * @param array<string, string> $params
     * @return Response
     */
    public function invoke(object $request, array $params): Response
    {
        $result = ($this->handler) ($request, $params);
        if ($result instanceof Response)
        {
            return $result;
        }
        return Response::json ($result);
    }
}

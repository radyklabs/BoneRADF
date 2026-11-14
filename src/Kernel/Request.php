<?php

declare(strict_types=1);

namespace Boneradf\Kernel;

/**
 * Immutable HTTP request representation.
 *
 * Built from the front controller; passed through middleware to handlers.
 * JSON body is decoded to an associative array (or null on parse failure).
 */
final class Request
{
    /** @param array<string, mixed> $body decoded JSON body, empty array if none */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $body = [],
        private readonly array $query = [],
        private readonly array $headers = [],
    ) {
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    /** @return array<string, mixed> */
    public function query(): array
    {
        return $this->query;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string)$key) === $lower) {
                return is_array($value) ? (string)$value[0] : (string)$value;
            }
        }
        return null;
    }

    public function queryParam(string $key): ?string
    {
        $val = $this->query[$key] ?? null;
        return is_array($val) ? null : (string)$val;
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $raw = file_get_contents('php://input') ?: '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $body = [];
        if ($raw !== '' && str_contains($contentType, 'application/json')) {
            $decoded = json_decode($raw, true);
            $body = is_array($decoded) ? $decoded : [];
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = (string)$value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string)$_SERVER['CONTENT_TYPE'];
        }
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = (string)$_SERVER['CONTENT_LENGTH'];
        }

        return new self($method, $path, $body, $_GET, $headers);
    }
}

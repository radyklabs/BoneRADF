<?php

declare(strict_types=1);

namespace Boneradf\Kernel;

use RuntimeException;

/**
 * Immutable HTTP response representation.
 *
 * JSON by default for a headless API framework; HTML view responses for
 * public landing pages are opt-in via Response::html() / Response::view().
 */
final class Response
{
    private const CONTENT_TYPE_JSON = 'application/json; charset=utf-8';
    private const CONTENT_TYPE_HTML = 'text/html; charset=utf-8';

    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly mixed $body = null,
        private readonly string $contentType = self::CONTENT_TYPE_JSON,
    ) {
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * The negotiated content type (`application/json` default,
     * `text/html` for view responses).
     */
    public function contentType(): string
    {
        return $this->contentType;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    /**
     * Serialize the response body to a string (JSON, or raw HTML for
     * view responses).
     */
    public function body(): string
    {
        if ($this->contentType === self::CONTENT_TYPE_HTML) {
            /** @var string $this->body */
            return $this->body;
        }
        return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Serialize the response body to a JSON string.
     */
    public function toJson(): string
    {
        return $this->body();
    }

    /**
     * Emit the response to the SAPI (headers + body).
     */
    public function emit(): void
    {
        http_response_code($this->status);
        header("Content-Type: {$this->contentType}");
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->body();
    }

    /**
     * Create a JSON response from an array or any JSON-encodable value.
     */
    public static function json(mixed $data, int $status = 200): self
    {
        return new self($status, $data);
    }

    /**
     * Create a no-content response (204 or custom status).
     */
    public static function noContent(int $status = 204): self
    {
        return new self($status, null);
    }

    /**
     * Create a raw HTML response (public landing pages).
     */
    public static function html(string $html, int $status = 200): self
    {
        return new self($status, $html, self::CONTENT_TYPE_HTML);
    }

    /**
     * Create a response from a view file under `src/Views/`
     * (public landing pages; zero data, plain output).
     *
     * The `$name` segment is constrained to alphanumeric + dash/underscore
     * (no separators, no `..`) so a view name can never traverse out of
     * the Views directory.
     */
    public static function view(string $name, int $status = 200): self
    {
        if ($name === '' || !preg_match('/^[A-Za-z0-9_\-]+$/', $name)) {
            throw new RuntimeException("Response::view: invalid view name '{$name}'.");
        }
        $file = defined('system') ? interlace(system, 'Views', "{$name}.php") : dirname(__DIR__) . "/Views/{$name}.php";
        if (!is_file($file)) {
            throw new RuntimeException("Response::view: view '{$name}' not found.");
        }
        ob_start();
        require $file;
        $html = (string) ob_get_clean();
        return self::html($html, $status);
    }

    /**
     * Create an error response with a uniform JSON envelope.
     *
     * @param array<string, mixed> $details optional structured context
     */
    public static function error(string $code, string $message, int $status, array $details = []): self
    {
        $envelope = [
            'error' => [
                'code'    => $code,
                'message' => $message,
            ],
        ];
        if ($details !== []) {
            $envelope['error']['details'] = $details;
        }
        return new self($status, $envelope);
    }
}

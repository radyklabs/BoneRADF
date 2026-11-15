<?php

declare(strict_types=1);

namespace Boneradf\Kernel;

/**
 * Immutable HTTP response representation.
 *
 * Always JSON for a headless API framework.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly mixed $body = null,
    ) {
    }

    public function status(): int
    {
        return $this->status;
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
     * Serialize the response body to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Emit the response to the SAPI (headers + body).
     */
    public function emit(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo $this->toJson();
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

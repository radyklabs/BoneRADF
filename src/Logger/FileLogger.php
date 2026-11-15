<?php

declare(strict_types=1);

namespace Boneradf\Logger;

/**
 * Structured JSON-line logger.
 *
 * Each log entry is one JSON object per line:
 *   {"ts":"...","level":"info","msg":"...","request_id":"...","context":{...}}
 *
 * Writes to a configurable file path. No secrets in default context.
 */
final class FileLogger implements Logger
{
    private string $path;
    private string $channel;

    public function __construct(string $path, string $channel = 'boneradf')
    {
        $this->path = $path;
        $this->channel = $channel;

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $entry = [
            'ts'         => date('c'),
            'level'      => $level,
            'channel'    => $this->channel,
            'msg'        => $message,
            'context'    => $context,
        ];

        // Redact potential secrets before writing
        $entry = $this->redact($entry);

        $line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        file_put_contents($this->path, $line . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Redact values that look like secrets.
     *
     * @param array<string, mixed> $entry
     * @return array<string, mixed>
     */
    private function redact(array $entry): array
    {
        $sensitive = ['secret', 'token', 'password', 'key', 'dsn', 'credential'];

        foreach ($entry as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $sk => $sv) {
                    foreach ($sensitive as $s) {
                        if (stripos((string)$sk, $s) !== false) {
                            $entry[$k][$sk] = '[REDACTED]';
                        }
                    }
                }
            }
        }
        return $entry;
    }

    /**
     * Get the log file path (useful for tests).
     */
    public function path(): string
    {
        return $this->path;
    }
}

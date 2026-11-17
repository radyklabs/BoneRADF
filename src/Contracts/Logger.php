<?php

declare(strict_types=1);

namespace Boneradf\Contracts;

/**
 * Minimal PSR-3-shaped log interface.
 *
 * Zero-dependency: PSR-shaped by convention (D2), not formal PSR-3 compliance.
 */
interface Logger
{
    public function debug(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;

    /**
     * Log at a given level.
     *
     * @param string $level   'debug' | 'info' | 'warning' | 'error'
     */
    public function log(string $level, string $message, array $context = []): void;
}

<?php

declare(strict_types=1);

/**
 * Custom PSR-4 autoloader for the Boneradf\ namespace.
 *
 * Zero dependencies — no composer required at runtime.
 * Maps Boneradf\X\Y to <root>/src/X/Y.php.
 */
function boneradf_autoload(string $class): void
{
    static $root;
    if ($root === null) {
        $root = dirname(__DIR__, 2) . '/src/';
    }

    $prefix = 'Boneradf\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $root . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
}

spl_autoload_register('boneradf_autoload');

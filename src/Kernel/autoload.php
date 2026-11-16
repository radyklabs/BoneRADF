<?php

declare(strict_types=1);

/**
 * Custom PSR-4 autoloader for the framework + module namespaces.
 *
 * Zero dependencies — no composer required at runtime.
 *   - `Boneradf\X\Y`   → <root>/src/X/Y.php       (core framework)
 *   - `Modules\Name\X` → <root>/modules/Name/src/X.php  (development layer)
 */
function boneradf_autoload(string $class): void
{
    static $root;
    if ($root === null) {
        $root = dirname(__DIR__, 2);
    }

    $corePrefix   = 'Boneradf\\';
    $modulePrefix = 'Modules\\';

    if (str_starts_with($class, $corePrefix)) {
        $relative = substr($class, strlen($corePrefix));
        $file     = $root . '/src/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
        return;
    }

    if (str_starts_with($class, $modulePrefix)) {
        // Modules\Auth\LoginService → modules/Auth/src/LoginService.php
        // Modules\Auth\Foo\Bar     → modules/Auth/src/Foo/Bar.php
        //
        // Note: basename() is NOT used (Windows treats `\` as a separator).
        // The module bootstrap class (e.g. Modules\Auth\AuthModule) may live
        // at modules/Auth/AuthModule.php (module root, not src/); the
        // autoloader checks src/ first, then the module root.
        $relative = substr($class, strlen($modulePrefix));
        $parts    = explode('\\', $relative, 2); // [moduleName, inner...]
        $moduleName = $parts[0];
        $inner      = $parts[1] ?? '';
        $relPath  = $inner !== '' ? str_replace('\\', '/', $inner) : $moduleName;
        // relPath = inner namespace path OR the module name (bootstrap class
        // at module root, e.g. "Auth" for Modules\Auth\AuthModule).
        $candidates = [
            $root . '/modules/' . $moduleName . '/src/' . $relPath . '.php',
            $root . '/modules/' . $moduleName . '/' . $relPath . '.php',
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                require $file;
                return;
            }
        }
    }
}

spl_autoload_register('boneradf_autoload');

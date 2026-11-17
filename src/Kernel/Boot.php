<?php declare (strict_types=1);

namespace Boneradf\Kernel;

use Boneradf\Container\Container;
use Boneradf\Kernel\Kernel;
use Boneradf\Manifest\Modules;
use Boneradf\Manifest\Routing;
// use Boneradf\Contracts\Module;
use Boneradf\Middleware\Pipeline;
use Boneradf\Router\Router;
use RuntimeException;

/**
 * Boot — static bootstrap entry point.
 *
 * init() is boot-scope only: register the PSR-4 autoloader, compose
 * Router / Pipeline / Container, discover + boot modules (topological
 * order), load default + module routes, and return a per-request Kernel.
 *
 * The returned Kernel is an instance — no Request/Response/container
 * state is held statically (ARCHITECTURE §4: container is request-scoped).
 */
final class Boot
{
    private static bool $initialized = false;
    private static bool $discoverModules = true;
    private static string $basepath = '';
    private static string $nssys = 'Boneradf\\';
    private static string $nsmod = 'Modules\\';

    public static function init (): Kernel
    {
        if (self::$initialized) {
            throw new RuntimeException ('Boot: init() already called.');
        }
        self::$initialized = true;

        self::$basepath = dirname(__DIR__, 2);

        self::registerAutoloader ();

        $router    = new Router ();
        $pipeline  = new Pipeline ();
        $container = new Container ();

        $seen = [];
        self::registerRoutes ($router, Routing::aliases (), $container, $seen);

        if (self::$discoverModules) {
            foreach (self::activeModules () as $name => $module) {
                self::registerRoutes ($router, self::moduleRoutes ($name), $container, $seen);
                $module->boot ($container, $router, $pipeline);
            }
        }

        return new Kernel ($router, $pipeline, $container);
    }

    /**
     * Disable module discovery + boot (test harness, no-DB CLI).
     * Default: discovery on (production path).
     */
    public static function withoutModules (): Kernel
    {
        self::$discoverModules = false;
        return self::init ();
    }

    /**
     * Build a Router holding system + module route aliases WITHOUT booting
     * any module (no container bindings, no DB). Used by `bone routes`.
     */
    public static function routes (): Router
    {
        // self::registerAutoloader ();

        $router    = new Router ();
        $container = new Container ();
        $seen      = [];

        self::registerRoutes ($router, Routing::aliases (), $container, $seen);

        foreach (Modules::list () as $name) {
            self::registerRoutes ($router, self::moduleRoutes ($name), $container, $seen);
        }

        return $router;
    }

    /**
     * PSR-4 autoloader — single source of truth for class resolution.
     *
     *   Boneradf\X\Y   → <root>/src/X/Y.php
     *   Modules\Name\X → <root>/modules/Name/src/X.php
     *                   → <root>/modules/Name/X.php   (module bootstrap class)
     */
    public static function autoload (string $class): void
    {
        require_once self::$basepath . '/.supports';

        if (!defined ('sep'))
        {
            define ('basepath', self::$basepath);
            define ('sep', DIRECTORY_SEPARATOR);

            $index_file = self::$basepath . '/public/index.php';

            foreach (\read_directories ($index_file) as $type => $dir)
            {
                define ($type, \interlace (basepath, $dir));
            }
        }

        // Konstanta (nssys / nsmod / system / modules) didefinisikan .config
        // pada entry web. CLI (tests, bin/bone) tidak me-load .config →
        // fallback ke nilai default yang sama dengan $directories di index.php.
        // $nsSys  = defined ('nssys') ? (string) nssys : 'Boneradf\\';
        // $nsMod  = defined ('nsmod') ? (string) nsmod : 'Modules\\';
        // $sysDir = defined ('system') ? (string) system : self::root () . '/src';

        // Contoh class: 'Boneradf\Kernel\Boot'
        //   nssys    = 'Boneradf\\'                       (namespace prefix + trailing \)
        //   system   = 'D:\Www\boneradf\app\src'          (absol ke folder src/)
        //   substr   = 'Kernel\Boot'                      (sisa namespace setelah prefix)
        //   +'.php'  = 'Kernel\Boot.php'                  (path relatif + ekstensi)
        //   $file    = 'D:\Www\boneradf\app\src\Kernel\Boot.php'
        if (str_starts_with ($class, self::$nssys))
        {
            $system_file = interlace ( system, substr ($class, strlen (self::$nssys)) . '.php');
            if (is_readable ($system_file)) {
                require $system_file;
            }
            return;
        }

        // Contoh class: 'Modules\Auth\LoginService'
        //   nsmod    = 'Modules\\'          (namespace prefix + trailing \)
        //   $relative= 'Auth\LoginService'  (sisa namespace setelah prefix)
        //   $parts   = ['Auth', 'LoginService']  (explode limit 2 → nama modul + sisanya)
        //   $name    = 'Auth'               (nama modul → folder modules/Auth/)
        //   $inner   = 'LoginService'       ('' kalau class = 'Modules\Auth' saja)
        //   $relPath = 'LoginService'       (path relatif dlm modul, \ → /)
        //   kandidat = modules/Auth/src/LoginService.php   ← cek pertama (PSR-4)
        //              modules/Auth/LoginService.php      ← cek kedua (bootstrap class)
        if (str_starts_with ($class, self::$nsmod))
        {
            $relative = substr ($class, strlen (self::$nsmod));
            $parts    = explode ('\\', $relative, 2);
            // $name     = $parts[0];
            // $inner    = $parts[1] ?? '';
            $modulename  = array_shift ($parts);
            $classname = end ($parts);
            // $relPath  = $inner !== '' ? str_replace ('\\', '/', $inner) : $name;

            foreach ([
                // self::modulesDir () . '/' . $name . '/src/' . $relPath . '.php',
                // self::modulesDir () . '/' . $name . '/' . $relPath . '.php',
                interlace (modules, $modulename, 'src', "{$classname}.php"),
                interlace (modules, $modulename, "{$classname}.php")
            ] as $file) {
                if (is_file ($file)) {
                    require $file;
                    return;
                }
            }
        }
    }

    private static function root (): string
    {
        return dirname (__DIR__, 2);
    }

    private static function modulesDir (): string
    {
        return self::root () . '/modules';
    }

    /**
     * Register default + module-declared aliases onto the router.
     *
     * Alias shape: 'METHOD /path/{param:type}' => [ControllerClass::class, 'method'].
     * Duplicate aliases fail boot.
     *
     * @param array<string, array{0: class-string, 1: string}> $aliases
     */
    /**
     * @param array<string, bool> $seen  shared duplicate tracker across all
     *                                   alias sources (system + modules)
     */
    private static function registerRoutes (Router $router, array $aliases, Container $container, array &$seen): void
    {
        foreach ($aliases as $alias => $handler) {
            [$method, $pattern] = self::splitAlias ($alias);
            $key = $method . ' ' . $pattern;
            if (isset ($seen[$key])) {
                throw new RuntimeException ("Boot: duplicate route alias '{$alias}'.");
            }
            $seen[$key] = true;
            $router->add ($method, $pattern, self::handler ($handler, $container), [], $alias);
        }
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    private static function handler (array $handler, Container $container): callable
    {
        return static function (Request $req, array $params) use ($handler, $container) {
            [$class, $method] = $handler;
            $controller = $container->has ($class)
                ? $container->get ($class)
                : new $class ();
            return $controller->{$method} ($req, $params);
        };
    }

    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    private static function moduleRoutes (string $name): array
    {
        $file = self::modulesDir () . '/' . $name . '/Config/routing.php';
        if (!is_file ($file)) {
            return [];
        }
        return require $file;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitAlias (string $alias): array
    {
        $parts = preg_split ('/\s+/', trim ($alias), 2);
        if (is_array ($parts) && count ($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            return [strtoupper ($parts[0]), $parts[1]];
        }
        throw new RuntimeException ("Boot: invalid route alias '{$alias}'.");
    }

    /**
     * Discover + topologically sort active modules declared in
     * src/Manifest/Modules.php. Fail-closed on missing dir / class, cycle,
     * or unknown dependency.
     *
     * @return array<string, Module>  keyed by module name, boot order.
     */
    private static function activeModules (): array
    {
        $active = Modules::list ();

        /** @var array<string, array{class: class-string, depends_on: list<string>}> $catalog */
        $catalog = [];
        foreach ($active as $name) {
            $catalog[$name] = self::moduleConfig ($name);
        }

        $indeg      = [];
        $dependents = [];
        foreach ($catalog as $name => $info) {
            $deps = array_values (array_filter ($info['depends_on'], fn ($d) => $d !== $name));
            $catalog[$name]['depends_on'] = $deps;
            $indeg[$name] = count ($deps);
            foreach ($deps as $dep) {
                if (!isset ($catalog[$dep])) {
                    throw new RuntimeException ("Boot: module '{$name}' depends on unknown module '{$dep}'.");
                }
                $dependents[$dep][] = $name;
            }
        }

        $ready = [];
        foreach ($catalog as $name => $_) {
            if ($indeg[$name] === 0) {
                $ready[] = $name;
            }
        }

        $order = [];
        while ($ready !== []) {
            $name    = array_shift ($ready);
            $order[] = $name;
            foreach ($dependents[$name] ?? [] as $child) {
                if (--$indeg[$child] === 0) {
                    $ready[] = $child;
                }
            }
        }

        if (count ($order) !== count ($catalog)) {
            $cyclic = array_values (array_diff (array_keys ($catalog), $order));
            throw new RuntimeException ('Boot: cyclic module dependency: ' . implode (', ', $cyclic));
        }

        $modules = [];
        foreach ($order as $name) {
            $class = $catalog[$name]['class'];
            $modules[$name] = new $class ();
        }
        return $modules;
    }

    /**
     * @return array{class: class-string, depends_on: list<string>}
     */
    private static function moduleConfig (string $name): array
    {
        $dir = self::modulesDir () . '/' . $name;
        if (!is_dir ($dir)) {
            throw new RuntimeException ("Boot: module '{$name}' not found at {$dir}.");
        }

        $manifest = $dir . '/Config/modules.php';
        if (!is_file ($manifest)) {
            throw new RuntimeException ("Boot: module '{$name}' missing Config/modules.php.");
        }

        $data  = require $manifest;
        $class = $data['class'] ?? 'Modules\\' . $name . '\\' . $name . 'Module';

        if (!class_exists ($class)) {
            throw new RuntimeException ("Boot: module '{$name}' class '{$class}' not found.");
        }

        /** @var class-string $class */
        return [
            'class'      => $class,
            'depends_on' => $data['depends_on'] ?? [],
        ];
    }

    public static function registerAutoloader (): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }
        $registered = true;
        spl_autoload_register ([self::class, 'autoload']);
    }
}

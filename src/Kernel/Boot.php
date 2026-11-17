<?php declare (strict_types=1);

namespace Boneradf\Kernel;

use Boneradf\Container\Container;
use Boneradf\Kernel\Kernel;
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
    private static bool $bootModules = true;
    private static string $basepath = '';
    private static string $nssys = 'Boneradf\\';
    private static string $nsmod = 'Modules\\';

    public static function init (): Kernel
    {
        if (self::$initialized)
        {
            throw new RuntimeException ('Boot: init() already called.');
        }
        
        self::$initialized = true;

        self::registerAutoloader ();

        // Fail-closed boot check (skipped when module boot is off — the
        // no-DB test harness path).
        if (self::$bootModules)
        {
            self::ensureSystemReady ();
        }

        $router    = new Router ();
        $pipeline  = new Pipeline ();
        $container = new Container ();

        $seen = [];
        
        self::registerRoutes ($router, Routing::aliases (), $container, $seen);

        if (self::$bootModules)
        {
            foreach (self::activeModules () as $name => $module)
            {
                self::registerRoutes ($router, self::moduleRoutes ($name), $container, $seen);
                $module->boot ($container, $router, $pipeline);
            }
        }

        return new Kernel ($router, $pipeline, $container);
    }

    /**
     * Disable module boot entirely (test harness, no-DB CLI).
     * Default: module boot on (production path).
     */
    public static function withoutModules (): Kernel
    {
        self::$bootModules = false;
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

        foreach (self::discoveredModules () as $name)
        {
            self::registerRoutes ($router, self::moduleRoutes ($name), $container, $seen);
        }

        return $router;
    }

    /**
     * PSR-4 autoloader — single source of truth for class resolution.
     *
     *   Boneradf\X\Y   → <root>/src/X/Y.php
     *   Modules\Name\X → <root>/modules/Name/X.php
     */
    public static function runAutoloader (string $class): void
    {
        if (str_starts_with ($class, self::$nssys))
        {
            $system_file = interlace ( system, substr ($class, strlen (self::$nssys)) . '.php');
            if (is_readable ($system_file))
            {
                require $system_file;
            }
            return;
        }

        if (str_starts_with ($class, self::$nsmod))
        {
            $module_file = interlace (modules, substr ($class, strlen (self::$nsmod)).'.php');
            if (is_readable ($module_file))
            {
                require $module_file;
                return;
            }
        }
    }

    // private static function root (): string
    // {
    //     return dirname (__DIR__, 2);
    // }

    // private static function modulesDir (): string
    // {
    //     return self::root () . '/modules';
    // }

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
        foreach ($aliases as $alias => $handler)
        {
            [$method, $pattern] = self::splitAlias ($alias);
            $key = $method . ' ' . $pattern;

            if (isset ($seen[$key]))
            {
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
        [$class, $method] = $handler;

        // Fail fast on a route that names a missing class/method — better a
        // clear RuntimeException at boot than a generic 500 at request time.
        if (!class_exists ($class))
        {
            throw new RuntimeException ("Boot: route handler class '{$class}' not found.");
        }
        if (!method_exists ($class, $method))
        {
            throw new RuntimeException ("Boot: route handler method '{$method}' not found on '{$class}'.");
        }

        return static function (Request $req, array $params) use ($class, $method, $container)
        {
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
        $module_file = interlace (modules, $name, 'Config/routing.php');
        
        if (!is_readable ($module_file)) {
            return [];
        }

        return require $module_file;
    }

    /**
     * @return array{0: string ProtocolMethod, 1: string UriSegments}
     */
    private static function splitAlias (string $alias): array
    {
        $parts = preg_split ('/\s+/', trim ($alias), 2);
        if (is_array ($parts) && count ($parts) === 2 && $parts[0] !== '' && $parts[1] !== '')
        {
            return [strtoupper ($parts[0]), $parts[1]];
        }
        throw new RuntimeException ("Boot: invalid route alias '{$alias}'.");
    }

    /**
     * Fail-closed boot check: when any module is active but its database is
     * not configured, throw a clear, actionable error *at boot time* —
     * not a per-request 500.
     *
     * "Active" = the module's `Config/routing.php` declares at least one
     * route. We don't connect to the DB here (cheap check, no PDO); the
     * first `Database::fromEnv()` call inside a module's `boot()` will do
     * the real connection. This guard only catches the "env is unset"
     * case, which is the common fresh-install failure mode.
     */
    private static function ensureSystemReady (): void
    {
        $active = [];
        foreach (self::discoveredModules () as $name)
        {
            if (self::moduleRoutes ($name) !== [])
            {
                $active[] = $name;
            }
        }
        if ($active === [])
        {
            return; // no active modules → no DB needed → nothing to check.
        }

        $dsn = getenv ('BONERADF_DB_DSN');
        if ($dsn === false || $dsn === '')
        {
            throw new RuntimeException (
                'Boot: active modules [' . implode (', ', $active) .
                '] require a database, but BONERADF_DB_DSN is not set. ' .
                'Provide it in the environment (or a Config).'
            );
        }
    }

    /**
     * System-level auto-discovery: every directory under modules/ that ships
     * `Config/modules.php` is a known module. Activation is separate — see
     * activeModules().
     *
     * @return list<string>  sorted module names.
     */
    private static function discoveredModules (): array
    {
        $entries = scandir (interlace (modules));
        if ($entries === false)
        {
            return [];
        }

        $names = [];
        foreach ($entries as $entry)
        {
            if ($entry === '.' || $entry === '..') { continue; }
            if (is_file (interlace (modules, $entry, 'Config/modules.php')))
            {
                $names[] = $entry;
            }
        }
        sort ($names);
        return $names;
    }

    /**
     * Resolve + topologically sort ACTIVE modules. A discovered module is
     * active only when its Config/routing.php declares at least one route —
     * that file is the module's own activation switch (ARCHITECTURE §5).
     * Inactive modules are never booted. Fail-closed on missing class, cycle,
     * or unknown/inactive dependency.
     *
     * @return array<string, Module>  keyed by module name, boot order.
     */
    private static function activeModules (): array
    {
        /** @var array<string, array{class: class-string, depends_on: list<string>}> $catalog */
        $catalog = [];
        foreach (self::discoveredModules () as $name)
        {
            if (self::moduleRoutes ($name) === [])
            {
                continue; // inert: no routes declared → never booted.
            }
            $catalog[$name] = self::moduleConfig ($name);
        }

        $indeg      = [];
        $dependents = [];
        foreach ($catalog as $name => $info)
        {
            $deps = array_values (array_filter ($info['depends_on'], fn ($d) => $d !== $name));
            $catalog[$name]['depends_on'] = $deps;
            $indeg[$name] = count ($deps);
            foreach ($deps as $dep)
            {
                if (!isset ($catalog[$dep]))
                {
                    throw new RuntimeException ("Boot: module '{$name}' depends on unknown module '{$dep}'.");
                }
                $dependents[$dep][] = $name;
            }
        }

        $ready = [];
        foreach ($catalog as $name => $_)
        {
            if ($indeg[$name] === 0)
            {
                $ready[] = $name;
            }
        }

        $order = [];
        while ($ready !== [])
        {
            $name    = array_shift ($ready);
            $order[] = $name;
            foreach ($dependents[$name] ?? [] as $child)
            {
                if (--$indeg[$child] === 0)
                {
                    $ready[] = $child;
                }
            }
        }

        if (count ($order) !== count ($catalog))
        {
            $cyclic = array_values (array_diff (array_keys ($catalog), $order));
            throw new RuntimeException ('Boot: cyclic module dependency: ' . implode (', ', $cyclic));
        }

        $modules = [];
        foreach ($order as $name)
        {
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
        $module_dir = interlace (modules, $name);

        if (!is_dir ($module_dir))
        {
            throw new RuntimeException ("Boot: module '{$name}' not found at {$module_dir}.");
        }

        $manifest = interlace ($module_dir, 'Config/modules.php');

        if (!is_file ($manifest))
        {
            throw new RuntimeException ("Boot: module '{$name}' missing Config/modules.php.");
        }

        $data  = require $manifest;
        $class = namespacify ($data['class'] ?? interlace ('Modules', $name, "{$name}Module"));

        if (!class_exists ($class))
        {
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

        // CLI (bin/bone) memanggil registerAutoloader() tanpa init() —
        // inisialisasi basepath di sini menjamin jalur autoload mana pun aman.
        if (self::$basepath === '') {
            self::$basepath = dirname(__DIR__, 2);
        }
        
        require_once self::$basepath . '/.supports';

        if (!defined ('sep'))
        {
            define ('basepath', self::$basepath);
            define ('sep', DIRECTORY_SEPARATOR);

            $index_file = self::$basepath . '/public/index.php';

            foreach (read_directories ($index_file) as $type => $dir)
            {
                define ($type, interlace (basepath, $dir));
            }
        }

        $registered = true;
        
        spl_autoload_register ([self::class, 'runAutoloader']);
    }
}

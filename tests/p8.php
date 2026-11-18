<?php

declare(strict_types=1);

/**
 * P8 verification tests: module auto-discovery + routing-based activation.
 *
 * Run:  php -d extension=pdo_mysql tests/p8.php
 * Exits non-zero on any failure.
 */

require dirname(__DIR__) . '/src/Kernel/Boot.php';

use Boneradf\Kernel\Boot;

// Ensure the fail-closed Config path is satisfied for this test (dev-only DSN).
require dirname(__DIR__) . '/tests/env.php';

$pass = 0;
$fail = 0;

function check(string $label, bool $ok): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
        echo "  PASS  {$label}\n";
    } else {
        $fail++;
        echo "  FAIL  {$label}\n";
    }
}

$root  = dirname(__DIR__);
$inert = $root . '/modules/InertTest';
@mkdir($inert . '/Config', 0777, true);
file_put_contents($inert . '/Config/modules.php', "<?php return ['class' => 'Modules\\InertTest\\InertTestModule', 'depends_on' => []];");
file_put_contents($inert . '/Config/routing.php', "<?php return [];\n");

try {
    // ----------------------------------------------------------
    // 1. Auto-discovery: both modules known without a central manifest
    // ----------------------------------------------------------
    $kernel = Boot::init();
    $routes = $kernel->router()->routes();
    $patterns = array_map(fn ($r) => $r->pattern(), $routes);

    check('discovery: Auth login route present', in_array('/api/auth/login', $patterns, true));
    check('discovery: Resources list route present', in_array('/api/resources', $patterns, true));

    // ----------------------------------------------------------
    // 2. Activation: Auth + Resources are active (routing.php non-empty)
    // ----------------------------------------------------------
    $authRoutes = array_filter($patterns, fn ($p) => str_starts_with($p, '/api/auth'));
    check('activation: Auth contributes its declared routes', count($authRoutes) === 4);

    $resRoutes = array_filter($patterns, fn ($p) => str_starts_with($p, '/api/resources'));
    check('activation: Resources contributes 7 routes', count($resRoutes) === 7);

    // ----------------------------------------------------------
    // 3. Inert module: empty routing.php → discovered but not booted
    // ----------------------------------------------------------
    check('activation: InertTest routes absent', !in_array('/api/inert-test', $patterns, true));
} finally {
    @unlink($inert . '/Config/modules.php');
    @unlink($inert . '/Config/routing.php');
    @rmdir($inert . '/Config');
    @rmdir($inert);
}

// ----------------------------------------------------------
// 4. Module scan mirrors `bone modules` (direct FS read — no exec())
//    A module with an empty routing.php is inactive with 0 routes; a
//    module with a non-empty routing.php is active.
// ----------------------------------------------------------
$scan = function (string $name) use ($root): array {
    $manifest = "{$root}/modules/{$name}/Config/modules.php";
    $routing  = "{$root}/modules/{$name}/Config/routing.php";
    if (!is_file($manifest)) { return ['missing', 0]; }
    $routes = is_file($routing) ? require $routing : [];
    if (!is_array($routes) || $routes === []) { return ['inactive', 0]; }
    return ['active', count($routes)];
};

[$authStatus, $authRoutes]     = $scan('Auth');
[$resStatus, $resRoutes]       = $scan('Resources');
[$testStatus, $testRoutes]     = $scan('Test');

check('module scan: Auth active', $authStatus === 'active' && $authRoutes >= 4);
check('module scan: Resources active', $resStatus === 'active' && $resRoutes === 7);
check('module scan: Test active', $testStatus === 'active');

// ----------------------------------------------------------
// 5. bone make:module generator (run into a throwaway name; cleaned up)
// ----------------------------------------------------------
$name = 'Gen' . strtoupper (substr (bin2hex (random_bytes (3)), 0, 4));
$cmd = PHP_BINARY . ' ' . escapeshellarg ($root . '/bin/bone') . ' make module ' . escapeshellarg ($name) . ' --with-db 2>&1';
$out = [];
exec ($cmd, $out, $gCode);

$genDir = $root . '/modules/' . $name;
$genFiles = [
    "Config/modules.php",
    "Config/routing.php",
    "{$name}Module.php",
    "Controllers/{$name}Controller.php",
    "Models/{$name}Model.php",
    "Services/{$name}Service.php",
    "seed.php",
];
$allPresent = is_dir ($genDir);
foreach ($genFiles as $rel) {
    $allPresent = $allPresent && is_file ("{$genDir}/{$rel}");
}
check('generator: exit 0 + all files created', $gCode === 0 && $allPresent);
check('generator: namespace pre-filled correctly',
      is_file ("{$genDir}/Controllers/{$name}Controller.php")
      && str_contains (file_get_contents ("{$genDir}/Controllers/{$name}Controller.php"), "namespace Modules\\{$name}\\Controllers;"));

// Overwrite guard: running again for the same name must fail non-zero.
exec ($cmd, $out, $gCode2);
check('generator: refuses overwrite', $gCode2 !== 0);

// Cleanup the probe module
foreach (['Controllers', 'Models', 'Services', 'Config'] as $sub) {
    @array_map ('unlink', glob ("{$genDir}/{$sub}/*.php"));
    @rmdir ("{$genDir}/{$sub}");
}
@unlink ("{$genDir}/{$name}Module.php");
@unlink ("{$genDir}/seed.php");
@rmdir ($genDir);

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
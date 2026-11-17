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

$modulesOut = null;
$cmd = PHP_BINARY . ' ' . escapeshellarg($root . '/bin/bone') . ' modules 2>&1';
exec($cmd, $lines, $boneCode);
$modulesOut = implode("\n", $lines);

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
// 4. bone modules: status + route count
// ----------------------------------------------------------
check('bone modules: exit 0', $boneCode === 0);
check('bone modules: Auth active (route count ≥4)', is_string($modulesOut) && (bool) preg_match('/Auth\s+active\s+[45]/', $modulesOut));
check('bone modules: InertTest inactive 0', is_string($modulesOut) && preg_match('/InertTest\s+inactive\s+0/', $modulesOut) === 1);
check('bone modules: header present', is_string($modulesOut) && str_contains($modulesOut, 'MODULE') && str_contains($modulesOut, 'STATUS'));

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);
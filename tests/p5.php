<?php

declare(strict_types=1);

/**
 * P5 verification tests: Cache + micro test runner.
 *
 * Run:  php tests/p5.php
 * Exits non-zero on any failure.
 */

require dirname(__DIR__) . '/src/Kernel/autoload.php';
require dirname(__DIR__) . '/src/Test/Runner.php';

use Boneradf\Cache\Cache;
use Boneradf\Cache\MemoryDriver;
use Boneradf\Cache\FileDriver;

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

// ------------------------------------------------------------------
// MemoryDriver
// ------------------------------------------------------------------
$mem = new MemoryDriver();

$mem->set('key1', 'value1');
check('mem: get returns stored value', $mem->get('key1') === 'value1');
check('mem: missing key → null', $mem->get('nope') === null);

$mem->set('t', 42, 1); // 1s TTL
check('mem: ttl value readable immediately', $mem->get('t') === 42);

$mem->delete('key1');
check('mem: delete removes key', $mem->get('key1') === null);

$mem->set('a', 1);
$mem->set('b', 2);
$mem->clear();
check('mem: clear removes all', $mem->get('a') === null && $mem->get('b') === null);

// ------------------------------------------------------------------
// FileDriver
// ------------------------------------------------------------------
$cacheDir = sys_get_temp_dir() . '/boneradf_test_cache_' . uniqid();
$file = new FileDriver($cacheDir);

$file->set('foo', 'bar');
check('file: get returns stored value', $file->get('foo') === 'bar');

$file->set('complex', ['a' => 1, 'b' => [2, 3]]);
check('file: array round-trip', $file->get('complex') === ['a' => 1, 'b' => [2, 3]]);

$file->delete('foo');
check('file: delete removes key', $file->get('foo') === null);

// TTL
$file->set('ttl-test', 'x', 0); // no expiry
check('file: no-expiry value persists', $file->get('ttl-test') === 'x');

// Missing key
check('file: missing key → null', $file->get('nonexistent') === null);

// Cleanup
@unlink($cacheDir . '/' . hash('sha256', 'ttl-test') . '.php');
@rmdir($cacheDir);

// ------------------------------------------------------------------
// Cache facade
// ------------------------------------------------------------------
$facade = new Cache(new MemoryDriver());
$facade->set('f1', 'fv');
check('facade: get returns value', $facade->get('f1') === 'fv');
$facade->delete('f1');
check('facade: delete works', $facade->get('f1') === null);

// ------------------------------------------------------------------
// Micro test runner — self-test
// ------------------------------------------------------------------
// Run a nested test session to verify test()/assert_eq()/test_run() work
// without actually exiting (we can't call test_run() here because it
// exits — instead, verify the runner is loadable and functions exist)
check('runner: test() callable', is_callable('\Boneradf\Test\test'));
check('runner: assert_true() callable', is_callable('\Boneradf\Test\assert_true'));
check('runner: assert_eq() callable', is_callable('\Boneradf\Test\assert_eq'));
check('runner: assert_null() callable', is_callable('\Boneradf\Test\assert_null'));
check('runner: assert_contains() callable', is_callable('\Boneradf\Test\assert_contains'));

// Verify assert_true throws on false
$threw = false;
try {
    \Boneradf\Test\assert_true(false, 'test assertion');
} catch (\RuntimeException) {
    $threw = true;
}
check('runner: assert_true throws on false', $threw === true);

$threw = false;
try {
    \Boneradf\Test\assert_eq(1, 2);
} catch (\RuntimeException) {
    $threw = true;
}
check('runner: assert_eq throws on mismatch', $threw === true);

$threw = false;
try {
    \Boneradf\Test\assert_null('not null');
} catch (\RuntimeException) {
    $threw = true;
}
check('runner: assert_null throws on non-null', $threw === true);

$threw = false;
try {
    \Boneradf\Test\assert_contains('xyz', 'hello');
} catch (\RuntimeException) {
    $threw = true;
}
check('runner: assert_contains throws on missing substring', $threw === true);

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

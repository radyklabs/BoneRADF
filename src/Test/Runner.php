<?php

declare(strict_types=1);

namespace Boneradf\Test;

/**
 * Minimal test runner — no PHPUnit (D7).
 *
 * Usage in test files:
 *   \Boneradf\Test\assert_eq(1, $value, 'should be 1');
 *   \Boneradf\Test\assert_true($condition, 'should be true');
 *
 * `assert()` is reserved — use `assert_true()` instead.
 */

/**
 * @var array<int, array{label: string, fn: callable}>
 */
global $boneradf_tests, $boneradf_failures;
$GLOBALS['boneradf_tests']   = $GLOBALS['boneradf_tests'] ?? [];
$GLOBALS['boneradf_failures'] = $GLOBALS['boneradf_failures'] ?? 0;

/**
 * Register a named test.
 */
function test(string $label, callable $fn): void
{
    $GLOBALS['boneradf_tests'][] = ['label' => $label, 'fn' => $fn];
}

/**
 * Run all registered tests; print results; exit non-zero on failure.
 */
function test_run(): void
{
    $tests = $GLOBALS['boneradf_tests'];
    $pass  = 0;
    $fail  = 0;

    foreach ($tests as $t) {
        try {
            $t['fn']();
            $pass++;
            echo "  PASS  {$t['label']}\n";
        } catch (\Throwable $e) {
            $fail++;
            echo "  FAIL  {$t['label']}\n        {$e->getMessage()}\n";
        }
    }

    echo "\n{$pass} passed, {$fail} failed.\n";
    exit($fail > 0 ? 1 : 0);
}

/**
 * Assert a condition is true. Fails the current test on false.
 */
function assert_true(bool $condition, string $message = ''): void
{
    if (!$condition) {
        throw new \RuntimeException($message !== '' ? $message : 'assertion failed');
    }
}

/**
 * Assert two values are strictly equal.
 */
function assert_eq(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        $msg = $message ?: 'assert_eq failed: ' . var_export($actual, true) . ' !== ' . var_export($expected, true);
        throw new \RuntimeException($msg);
    }
}

/**
 * Assert a value is null.
 */
function assert_null(mixed $value, string $message = ''): void
{
    if ($value !== null) {
        $msg = $message ?: 'expected null, got ' . var_export($value, true);
        throw new \RuntimeException($msg);
    }
}

/**
 * Assert a string contains a substring.
 */
function assert_contains(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        $msg = $message ?: "'{$needle}' not found in '{$haystack}'";
        throw new \RuntimeException($msg);
    }
}

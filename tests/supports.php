<?php

declare(strict_types=1);

/**
 * Bootstrap helper (.supports) checks.
 *
 * Run:  php tests/supports.php
 * Exits non-zero on any failure.
 */

require dirname(__DIR__) . '/.supports';

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

$index = dirname(__DIR__) . '/public/index.php';

// --- read_directories ---
$dirs = read_directories($index);
check('parses index.php $directories', $dirs === ['system' => 'src', 'modules' => 'modules']);
check('missing file returns []', read_directories(__DIR__ . '/no-such-file.php') === []);

$scratch = sys_get_temp_dir() . '/bone-supports-none.php';
file_put_contents($scratch, '<?php $x = 1;');
check('file without $directories returns []', read_directories($scratch) === []);
unlink($scratch);

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

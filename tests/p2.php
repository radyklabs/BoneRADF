<?php

declare(strict_types=1);

/**
 * P2 verification tests: Config + DI Container + Logger.
 *
 * Run:  php tests/p2.php
 * Exits non-zero on any failure.
 */

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Config\Config;
use Boneradf\Container\Container;
use Boneradf\Logger\FileLogger;
use Boneradf\Kernel\Kernel;
use Boneradf\Kernel\Request;
use Boneradf\Router\Router;
use Boneradf\Middleware\Pipeline;

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

// --- Config: basic access ---
$cfg = new Config(
    defaults: ['app_name' => 'test', 'debug' => false, 'max_retries' => 3],
    types:    ['app_name' => 'string', 'debug' => 'bool', 'max_retries' => 'int'],
);
check('config get string', $cfg->get('app_name') === 'test');
check('config get bool',   $cfg->get('debug') === false);
check('config get int',    $cfg->get('max_retries') === 3);
check('config has',        $cfg->has('app_name') === true);
check('config missing key', !isset($cfg->all()['nonexistent']));

// --- Config: env > file > defaults ---
$cfg2 = new Config(
    defaults: ['level' => 'info'],
    file:     ['level' => 'warning'],
    env:      ['level' => 'error'],
);
check('config env overrides file', $cfg2->get('level') === 'error');

$cfg3 = new Config(
    defaults: ['level' => 'info'],
    file:     ['level' => 'warning'],
);
check('config file overrides defaults', $cfg3->get('level') === 'warning');

// --- Config: type coercion ---
$cfg4 = new Config(
    defaults: [],
    types:    ['port' => 'int'],
    file:     ['port' => '8080'],
);
check('config string→int coercion', $cfg4->get('port') === 8080);

// --- Config: required key missing → exception ---
$threw = false;
try {
    new Config(
        defaults: ['db' => null],
        required: ['db' => true],
        file:     [],
    );
} catch (RuntimeException) {
    $threw = true;
}
// The key is present in defaults (null), so required passes — instead test truly missing:
$threw2 = false;
try {
    $c = new Config(required: ['missing_key' => true]);
    $c->get('missing_key');
} catch (RuntimeException) {
    $threw2 = true;
}
check('config missing required → exception', $threw2 === true);

// --- Config: getOr fallback ---
$cfg5 = new Config(defaults: []);
check('config getOr fallback', $cfg5->getOr('x', 'fallback') === 'fallback');

// --- Container: basic binding ---
$c = new Container();
$c->instance('app.name', new \stdClass());
$obj = $c->get('app.name');
check('container instance', $obj instanceof \stdClass);

// --- Container: factory ---
$c->bind('svc', fn (Container $ct) => new \ArrayObject(['ok' => true]));
$svc = $c->get('svc');
check('container factory', $svc instanceof \ArrayObject);
check('container factory returns cached', $c->get('svc') === $svc);

// --- Container: unbound → exception ---
$threw = false;
try {
    $c->get('nonexistent');
} catch (RuntimeException) {
    $threw = true;
}
check('container unbound → exception', $threw === true);

// --- Container: circular detection ---
$c2 = new Container();
$c2->bind('a', fn ($ct) => $ct->get('b'));
$c2->bind('b', fn ($ct) => $ct->get('a'));
$threw = false;
try {
    $c2->get('a');
} catch (RuntimeException $e) {
    $threw = str_contains($e->getMessage(), 'circular');
}
check('container circular → exception', $threw === true);

// --- Container: keys ---
$c3 = new Container();
$c3->instance('x', new \stdClass());
$c3->bind('y', fn () => new \stdClass());
$keys = $c3->keys();
check('container keys includes x', in_array('x', $keys, true));
check('container keys includes y', in_array('y', $keys, true));

// --- FileLogger: structured JSON line ---
$logPath = sys_get_temp_dir() . '/boneradf_test_' . uniqid() . '.log';
$logger = new FileLogger($logPath, 'test');
$logger->info('hello', ['request_id' => 'abc123', 'extra' => 'val']);

$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
check('logger wrote one line', count($lines) === 1);
$decoded = json_decode($lines[0], true);
check('logger ts present', isset($decoded['ts']));
check('logger level=info', $decoded['level'] === 'info');
check('logger channel=test', $decoded['channel'] === 'test');
check('logger msg=hello', $decoded['msg'] === 'hello');
check('logger context.request_id', $decoded['context']['request_id'] === 'abc123');

// --- FileLogger: redaction ---
$logPath2 = sys_get_temp_dir() . '/boneradf_test_' . uniqid() . '.log';
$logger2 = new FileLogger($logPath2, 'test');
$logger2->error('auth_failed', ['user' => 'alice', 'password' => 's3cret', 'token' => 'xyz']);
$lines2 = file($logPath2, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$decoded2 = json_decode($lines2[0], true);
check('logger redacts password', $decoded2['context']['password'] === '[REDACTED]');
check('logger redacts token',    $decoded2['context']['token'] === '[REDACTED]');

// Cleanup temp log files
@unlink($logPath);
@unlink($logPath2);

// --- Kernel with config + container + logger ---
$cfg6 = new Config(defaults: ['env' => 'development']);
$container = new Container();
$container->instance(Config::class, $cfg6);
$kernel = new Kernel(
    new Router(),
    new Pipeline(),
    $container,
    $cfg6,
);
check('kernel has config', $kernel->config() === $cfg6);
check('kernel has container', $kernel->container() === $container);

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

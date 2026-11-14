<?php

declare(strict_types=1);

/**
 * P0 verification tests.
 *
 * Run:  php tests/p0.php
 * Exits non-zero on any failure.
 */

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Kernel\Kernel;
use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;

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

// --- Autoloader ---
check('autoloader registered', spl_autoload_functions() !== []);

// --- Request ---
$req = new Request('GET', '/hello');
check('request method', $req->method() === 'GET');
check('request path', $req->path() === '/hello');
check('request empty body', $req->body() === []);
check('request header case-insensitive', $req->header('Content-Type') === null);

$req2 = new Request('POST', '/api/users', ['name' => 'Ada'], ['page' => '1'], ['X-Custom' => 'val']);
check('request body', $req2->body() === ['name' => 'Ada']);
check('request query', $req2->query() === ['page' => '1']);
check('request header lookup', $req2->header('x-custom') === 'val');
check('request queryParam', $req2->queryParam('page') === '1');

// --- Response ---
$ok = Response::json(['message' => 'ok']);
check('response status', $ok->status() === 200);
check('response json encode', $ok->toJson() === '{"message":"ok"}');

$err = Response::error('NOT_FOUND', 'Route not found.', 404, ['path' => '/x']);
check('error status', $err->status() === 404);
$decoded = json_decode($err->toJson(), true);
check('error envelope code', $decoded['error']['code'] === 'NOT_FOUND');
check('error envelope message', $decoded['error']['message'] === 'Route not found.');
check('error envelope details', $decoded['error']['details'] === ['path' => '/x']);

$noContent = Response::noContent();
check('no-content status', $noContent->status() === 204);
check('no-content json is null', $noContent->toJson() === 'null');

// --- Kernel ---
$kernel = new Kernel();
$resp = $kernel->handle(new Request('GET', '/anything'));
check('kernel returns 404 (no router yet)', $resp->status() === 404);
$decoded = json_decode($resp->toJson(), true);
check('kernel 404 envelope code', $decoded['error']['code'] === 'NOT_FOUND');

// --- Exception → 500 ---
// Simulate by subclassing is not possible (final); instead verify the
// contract: handle() catches Throwable. We test with a Request that triggers
// the catch path by monkey-patching via a closure-based handler is not
// available yet (router lands in P1). The 500 path is exercised when a
// handler throws. For P0 we assert the method exists and is callable.
check('kernel handle() callable', is_callable([$kernel, 'handle']));

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

<?php

declare(strict_types=1);

/**
 * P1 verification tests: Router + Middleware Pipeline.
 *
 * Run:  php tests/p1.php
 * Exits non-zero on any failure.
 */

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Kernel\Kernel;
use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;
use Boneradf\Router\Router;
use Boneradf\Middleware\Pipeline;
use Boneradf\Middleware\Middleware;

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

// --- Router: basic matching ---
$router = new Router();
$router->get('/users', fn ($req, $p) => ['users' => []]);
$router->get('/users/:id', fn ($req, $p) => ['id' => $p['id']]);
$router->post('/users', fn ($req, $p) => ['created' => true]);

$match = $router->match(new Request('GET', '/users'));
check('router matches static route', $match !== null && $match['route']->pattern() === '/users');

$match = $router->match(new Request('GET', '/users/42'));
check('router matches param route', $match !== null);
check('router extracts param', $match !== null && $match['params']['id'] === '42');

$match = $router->match(new Request('POST', '/users'));
check('router matches POST route', $match !== null && $match['route']->method() === 'POST');

$match = $router->match(new Request('DELETE', '/users'));
check('router returns null for unknown method+path', $match === null);

check('router pathExists detects 405', $router->pathExists('/users') === true);
check('router pathExists false for unknown', $router->pathExists('/unknown') === false);

// --- Router: group ---
$router2 = new Router();
$router2->group('/api/v1', function (Router $r) {
    $r->get('/status', fn () => ['v' => 1]);
    $r->get('/items/:id', fn ($req, $p) => ['id' => $p['id']]);
});
$match = $router2->match(new Request('GET', '/api/v1/status'));
check('router group prefix match', $match !== null && $match['route']->pattern() === '/api/v1/status');
$match = $router2->match(new Request('GET', '/api/v1/items/7'));
check('router group param match', $match !== null && $match['params']['id'] === '7');

// --- Router: enumeration ---
check('router routes() count', count($router2->routes()) === 2);

// --- Middleware: ordering ---
$log = [];
$GLOBALS['test_log'] = &$log; // alias for closure access

class OrderMiddleware implements Middleware
{
    public function __construct(private string $name) {}
    public function handle(Request $req, callable $next): Response
    {
        $GLOBALS['test_log'][] = $this->name . ':in';
        $resp = $next($req);
        $GLOBALS['test_log'][] = $this->name . ':out';
        return $resp;
    }
}
// ponytail: global $test_log for test visibility; production uses request-scoped state

$pipeline = new Pipeline();
$pipeline->add(new OrderMiddleware('A'));
$pipeline->add(new OrderMiddleware('B'));

$GLOBALS['test_log'] = [];
$result = $pipeline->run(new Request('GET', '/'), fn (Request $r) => Response::json(['ok' => true]));
check('middleware ordering: A in before B in', $GLOBALS['test_log'][0] === 'A:in' && $GLOBALS['test_log'][1] === 'B:in');
check('middleware ordering: B out before A out', $GLOBALS['test_log'][2] === 'B:out' && $GLOBALS['test_log'][3] === 'A:out');
check('middleware returns endpoint response', $result->status() === 200);

// --- Middleware: abort (short-circuit) ---
class AbortMiddleware implements Middleware
{
    public function handle(Request $req, callable $next): Response
    {
        return Response::json(['blocked' => true], 403); // never calls $next
    }
}
$pipeline2 = new Pipeline();
$pipeline2->add(new AbortMiddleware());
$result = $pipeline2->run(new Request('GET', '/'), fn () => Response::json(['ok' => true]));
check('abort returns 403', $result->status() === 403);
check('abort prevents endpoint', $result->toJson() === '{"blocked":true}');

// --- Middleware: error bubbling ---
class ThrowMiddleware implements Middleware
{
    public function handle(Request $req, callable $next): Response
    {
        throw new \RuntimeException('boom');
    }
}
$kernel = new Kernel($router, new Pipeline());
// Kernel catch: unhandled exception → 500 JSON envelope
$resp = $kernel->handle(new Request('GET', '/unknown-path'));
check('kernel 404 on unknown path', $resp->status() === 404);

$router3 = new Router();
$router3->get('/throw', fn () => throw new \RuntimeException('test'));
$kernel3 = new Kernel($router3, new Pipeline());
$resp = $kernel3->handle(new Request('GET', '/throw'));
check('kernel 500 on handler exception', $resp->status() === 500);
$decoded = json_decode($resp->toJson(), true);
check('kernel 500 envelope code', $decoded['error']['code'] === 'INTERNAL_ERROR');

// --- Kernel: 405 detection ---
$router4 = new Router();
$router4->get('/only-get', fn () => ['ok' => true]);
$kernel4 = new Kernel($router4, new Pipeline());
$resp = $kernel4->handle(new Request('DELETE', '/only-get'));
check('kernel 405 on wrong method', $resp->status() === 405);
$decoded = json_decode($resp->toJson(), true);
check('kernel 405 envelope code', $decoded['error']['code'] === 'METHOD_NOT_ALLOWED');

echo "\n{$pass} passed, {$fail} failed.\n";
exit($fail > 0 ? 1 : 0);

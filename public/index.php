<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Kernel\Kernel;
use Boneradf\Kernel\Request;
use Boneradf\Router\Router;
use Boneradf\Middleware\Pipeline;
use Boneradf\Container\Container;
use Boneradf\Module\Module;

$router   = new Router();
$pipeline = new Pipeline();
$container = new Container();

// Register default routes
$router->get('/health', fn () => ['status' => 'ok']);
$router->get('/hello',  fn () => ['message' => 'Hello, world!']);

// ------------------------------------------------------------------
// Module composition root (ARCHITECTURE §5).
//
// Each development module self-registers its routes / middleware / bindings in
// boot(). Registration is explicit here (not auto-discovery) so the set of
// active modules is greppable and reviewable. To add a module, append its
// class to $modules.
//
// ponytail: hardcoded module list; upgrade to a config-driven manifest when
// module selection becomes environment-dependent (P2.1 .env loader).
// ------------------------------------------------------------------
/** @var array<int, Module> $modules */
$modules = [
    new \Modules\Auth\AuthModule(),
    new \Modules\Resources\ResourcesModule(),
];

foreach ($modules as $module) {
    $module->boot($container, $router, $pipeline);
}

$kernel   = new Kernel($router, $pipeline, $container);
$response = $kernel->handle(Request::fromGlobals());
$response->emit();

<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Kernel\Kernel;
use Boneradf\Kernel\Request;
use Boneradf\Router\Router;
use Boneradf\Middleware\Pipeline;
use Boneradf\Container\Container;
use Boneradf\Config\Config;
use Boneradf\Logger\FileLogger;

$router   = new Router();
$pipeline = new Pipeline();

// Register default routes
$router->get('/health', fn () => ['status' => 'ok']);
$router->get('/hello',  fn () => ['message' => 'Hello, world!']);

// ponytail: no .env loader yet (P2.1 deliverable) — Config uses empty defaults
// upgrade path: load .env into $configData before passing to Config

$kernel   = new Kernel($router, $pipeline);
$response = $kernel->handle(Request::fromGlobals());
$response->emit();

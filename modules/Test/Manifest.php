<?php

declare(strict_types=1);

namespace Modules\Test;

use Boneradf\Container\Container;
use Boneradf\Database\Database;
use Boneradf\Middleware\Pipeline;
use Boneradf\Contracts\Module;
use Boneradf\Router\Router;
use Modules\Test\Controllers\TestController;
// use Modules\Test\Models\TestModel;
// use Modules\Test\Services\Testervice;

final class Manifest implements Module
{
    public function name(): string
    {
        return 'test';
    }

    public function boot(Container $container, Router $router, Pipeline $pipeline): void
    {
        // $service = new TestService(new TestModel(Database::fromEnv()));
        // $container->instance('Test.service', $service);
        $container->instance(TestController::class, new TestController());
    }
}

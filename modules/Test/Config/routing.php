<?php declare (strict_types=1);

namespace Modules\Resources;

use Modules\Test\Controllers\TestController;

/**
 * Resources module route contributions.
 *
 * @return array<string, array{0: class-string, 1: string}>
 */
return [
    'GET    /test'        => [TestController::class, 'index'],
];

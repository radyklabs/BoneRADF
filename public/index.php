<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Kernel/autoload.php';

use Boneradf\Kernel\Kernel;
use Boneradf\Kernel\Request;
use Boneradf\Kernel\Response;

$kernel = new Kernel();
$response = $kernel->handle(Request::fromGlobals());
$response->emit();

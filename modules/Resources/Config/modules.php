<?php declare (strict_types=1);

/**
 * Resources module manifest.
 *
 * @return array{class: class-string, depends_on: list<string>, routes: bool}
 */
return [
    'class'      => 'Modules\\Resources\\ResourcesModule',
    'depends_on' => [],
    'routes'     => true,
];

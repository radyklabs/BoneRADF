<?php declare (strict_types=1);

/**
 * Auth module manifest.
 *
 * Discovered by Boot::activeModules() from src/Manifest/Modules.php.
 * `depends_on` is ordered topologically; cycles fail closed.
 *
 * @return array{class: class-string, depends_on: list<string>, routes: bool}
 */
return [
    'class'      => 'Modules\\Auth\\AuthModule',
    'depends_on' => [],
    'routes'     => true,
];

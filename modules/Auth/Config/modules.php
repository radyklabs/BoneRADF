<?php declare (strict_types=1);

/**
 * Auth module manifest — declares the module class + dependencies.
 *
 * Auto-discovered by Boot::activeModules() from the module's
 * Config/modules.php manifest.
 *
 * Activation is separate: Config/routing.php must declare one or more
 * routes, or the module stays inert (never booted).
 *
 * @return array{class: class-string, depends_on: list<string>}
 */
return [
    'class'      => 'Modules\\Auth\\AuthModule',
    'depends_on' => [],
];
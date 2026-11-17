<?php declare (strict_types=1);

/**
 * Resources module manifest — declares the module class + dependencies.
 *
 * Auto-discovered by Boot::activeModules() from each modules/ entry's
 * Config/modules.php.
 * Activation is separate: Config/routing.php must declare ≥1 route or the
 * module stays inert (never booted).
 *
 * @return array{class: class-string, depends_on: list<string>}
 */
return [
    'class'      => 'Modules\\Resources\\ResourcesModule',
    'depends_on' => [],
];

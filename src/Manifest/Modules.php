<?php declare (strict_types=1);

namespace Boneradf\Manifest;

/**
 * Active module list (ARCHITECTURE §5). This is the single gate:
 * a module not listed here is never booted and cannot register routes.
 *
 * Order is not significant — Boot topologically sorts by each module's
 * modules.php `depends_on`. Add a module by appending its name; growth is
 * one line per module here plus its modules/<Name>/Config/*.php files.
 */
final class Modules
{
    /**
     * @return list<string>
     */
    public static function list (): array
    {
        return [
            'Auth',
            'Resources',
        ];
    }
}

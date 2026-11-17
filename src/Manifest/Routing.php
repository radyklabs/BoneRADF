<?php declare (strict_types=1);

namespace Boneradf\Manifest;

use Boneradf\Controller\DefaultController;

/**
 * System-level default routes (ARCHITECTURE §3 step 6).
 *
 * Alias shape: 'METHOD /path/{param:type}' => [ControllerClass::class, 'method'].
 * Module routes are additive: modules/<Name>/Config/routing.php declares its
 * own aliases; Boot merges with the system list. Duplicate aliases fail boot.
 *
 * @return array<string, array{0: class-string, 1: string}>
 */
final class Routing
{
    public static function aliases (): array
    {
        return [
            'GET /'       => [DefaultController::class, 'index'],
            'GET /health' => [DefaultController::class, 'health'],
        ];
    }
}

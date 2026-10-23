<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Contena\Frontend\Controller\StorybookController;

/**
 * @internal
 */
final class StorybookRouteScopeAllowList implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === StorybookController::class;
    }
}

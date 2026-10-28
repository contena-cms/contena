<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Contena\Storefront\Controller\StorybookController;

/**
 * @internal
 */
#[Package('discovery')]
final class StorybookRouteScopeAllowList implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === StorybookController::class;
    }
}

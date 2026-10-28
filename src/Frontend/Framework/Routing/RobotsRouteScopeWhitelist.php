<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Contena\Storefront\Controller\RobotsController;

#[Package('discovery')]
class RobotsRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === RobotsController::class;
    }
}

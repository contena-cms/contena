<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Contena\Frontend\Controller\RobotsController;

class RobotsRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === RobotsController::class;
    }
}

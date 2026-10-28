<?php declare(strict_types=1);

namespace Contena\Core\Profiling\Routing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Contena\Core\Profiling\Controller\ProfilerController;

#[Package('framework')]
class ProfilerWhitelist implements RouteScopeWhitelistInterface
{
    public function applies(string $controllerClass): bool
    {
        return $controllerClass === ProfilerController::class;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
class SymfonyRouteScopeWhitelist implements RouteScopeWhitelistInterface
{
    /**
     * {@inheritdoc}
     */
    public function applies(string $controllerClass): bool
    {
        return str_starts_with($controllerClass, 'Symfony\\');
    }
}

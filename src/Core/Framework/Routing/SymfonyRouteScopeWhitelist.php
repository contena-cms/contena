<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing;

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

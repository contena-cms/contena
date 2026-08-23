<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Routing\AbstractRouteScope;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RouteScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeScopeDefinitions = $container->findTaggedServiceIds('contena.route_scope');

        $apiPrefixes = [];
        foreach (array_keys($routeScopeDefinitions) as $definition) {
            $routeScope = $container->get($definition);

            if (!$routeScope instanceof AbstractRouteScope) {
                continue;
            }

            $apiPrefixes = array_merge($apiPrefixes, $routeScope->getRoutePrefixes());
        }

        $container->setParameter('contena.routing.registered_api_prefixes', $apiPrefixes);
    }
}

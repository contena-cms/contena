<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection\CompilerPass;

use Contena\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\AbstractRouteScope;
use Contena\Core\Framework\Routing\ApiContextRouteScopeDependant;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class RouteScopeCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $routeScopeDefinitions = $container->findTaggedServiceIds('contena.route_scope');

        $apiPrefixes = [];
        $apiContextPrefixes = [];
        foreach (array_keys($routeScopeDefinitions) as $definition) {
            $routeScope = $container->get($definition);

            if (!$routeScope instanceof AbstractRouteScope) {
                continue;
            }

            $apiPrefixes = array_merge($apiPrefixes, $routeScope->getRoutePrefixes());

            if ($routeScope instanceof ApiContextRouteScopeDependant) {
                $apiContextPrefixes = array_merge($apiContextPrefixes, $routeScope->getRoutePrefixes());
            }
        }

        $container->setParameter('contena.routing.registered_api_prefixes', $apiPrefixes);
        $container->setParameter('contena.routing.api_context_route_prefixes', array_values(array_unique($apiContextPrefixes)));
    }
}

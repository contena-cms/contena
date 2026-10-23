<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\RouteScopeCompilerPass;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\RouteScope;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(RouteScopeCompilerPass::class)]
class RouteScopeCompilerPassTest extends TestCase
{
    public function testCollectsPrefixesOfAllRouteScopesAndOfApiContextRouteScopes(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ApiRouteScope::class, (new Definition(ApiRouteScope::class))->addTag('contena.route_scope'));
        $container->setDefinition(StoreApiRouteScope::class, (new Definition(StoreApiRouteScope::class))->addTag('contena.route_scope'));
        $container->setDefinition(RouteScope::class, (new Definition(RouteScope::class))->addTag('contena.route_scope'));

        (new RouteScopeCompilerPass())->process($container);

        static::assertSame(
            ['api', 'sw-domain-hash.html', 'store-api', '_wdt', '_profiler', '_error'],
            $container->getParameter('contena.routing.registered_api_prefixes')
        );
        static::assertSame(
            ['api', 'sw-domain-hash.html'],
            $container->getParameter('contena.routing.api_context_route_prefixes')
        );
    }
}

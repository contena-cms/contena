<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\MaintenanceController;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(MaintenanceController::class)]
class MaintenanceControllerTest extends TestCase
{
    public function testMaintenanceRedirectToShopWithRedirectTo(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('shouldRedirectToShop')
            ->willReturn(true);

        $controller = new MaintenanceController(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            $maintenanceModeResolver
        );

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/foo/generated');

        $request = new Request(
            [
                'redirectTo' => 'foo',
                'redirectParameters' => ['foo' => 'bar'],
            ]
        );

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));

        $controller->setContainer($container);

        $context = Generator::generateChannelContext();

        $response = $controller->renderMaintenancePage($request, $context);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/foo/generated', $response->getTargetUrl());
    }
}

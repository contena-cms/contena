<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\ContentSystem\Channel\AbstractContentRoute;
use Contena\Core\Framework\ContentSystem\Channel\ContentRouteResponse;
use Contena\Core\Framework\ContentSystem\Output\Struct\ContentPage;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\MaintenanceController;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Maintenance\MaintenancePage;
use Contena\Frontend\Page\Maintenance\MaintenancePageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(MaintenanceController::class)]
class MaintenanceControllerTest extends TestCase
{
    public function testMaintenanceRedirectToFrontendWithRedirectTo(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver->method('shouldRedirectToFrontend')->willReturn(true);

        $controller = new MaintenanceController(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            $maintenanceModeResolver,
            static::createStub(AbstractContentRoute::class),
        );

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/foo/generated');

        $request = new Request([
            'redirectTo' => 'foo',
            'redirectParameters' => ['foo' => 'bar'],
        ]);

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $controller->setContainer($container);

        $response = $controller->renderMaintenancePage($request, static::createStub(ChannelContext::class));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/foo/generated', $response->getTargetUrl());
    }

    public function testMaintenancePageWithoutAssignedLandingPageReturnsUnavailableResponse(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())->method('getString')->willReturn('');
        $resolver = static::createStub(MaintenanceModeResolver::class);
        $resolver->method('shouldRedirectToFrontend')->willReturn(false);
        $controller = new MaintenanceControllerTestClass(
            $systemConfig,
            static::createStub(MaintenancePageLoader::class),
            $resolver,
            static::createStub(AbstractContentRoute::class),
        );
        $this->setRenderContainer($controller);

        $response = $controller->renderMaintenancePage(new Request(), static::createStub(ChannelContext::class));

        static::assertNotNull($response);
        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        static::assertSame('3600', $response->headers->get('Retry-After'));
    }

    public function testMaintenancePagePassesAssignedContentLayoutToTemplate(): void
    {
        $context = static::createStub(ChannelContext::class);
        $systemConfig = static::createStub(SystemConfigService::class);
        $systemConfig->method('getString')->willReturn('landing-page-id');
        $pageLoader = static::createStub(MaintenancePageLoader::class);
        $page = static::createStub(MaintenancePage::class);
        $pageLoader->method('load')->willReturn($page);
        $contentPage = new ContentPage('layout-id', [], 'maintenance', null);
        $contentRoute = static::createStub(AbstractContentRoute::class);
        $contentRoute->method('load')->willReturn(new ContentRouteResponse($contentPage));
        $resolver = static::createStub(MaintenanceModeResolver::class);
        $resolver->method('shouldRedirectToFrontend')->willReturn(false);
        $controller = new MaintenanceControllerTestClass($systemConfig, $pageLoader, $resolver, $contentRoute);
        $this->setRenderContainer($controller, $context);

        $response = $controller->renderMaintenancePage(new Request(), $context);

        static::assertNotNull($response);
        static::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        static::assertSame($page, $controller->renderFrontendParameters['page']);
        static::assertSame($contentPage, $controller->renderFrontendParameters['contentPage']);
        static::assertTrue($controller->renderFrontendParameters['isNewContentStructure']);
    }

    public function testSinglePageRequiresId(): void
    {
        $controller = new MaintenanceControllerTestClass(
            static::createStub(SystemConfigService::class),
            static::createStub(MaintenancePageLoader::class),
            static::createStub(MaintenanceModeResolver::class),
            static::createStub(AbstractContentRoute::class),
        );

        static::expectExceptionObject(RoutingException::missingRequestParameter('id'));
        $controller->renderSinglePage('', new Request(), static::createStub(ChannelContext::class));
    }

    public function testSinglePageAddsMaintenanceAllowlistHeader(): void
    {
        $context = static::createStub(ChannelContext::class);
        $resolver = static::createStub(MaintenanceModeResolver::class);
        $pageLoader = static::createStub(MaintenancePageLoader::class);
        $pageLoader->method('load')->willReturn(static::createStub(MaintenancePage::class));
        $contentRoute = static::createStub(AbstractContentRoute::class);
        $contentRoute->method('load')->willReturn(new ContentRouteResponse(new ContentPage('layout-id', [], 'maintenance', null)));
        $controller = new MaintenanceControllerTestClass(static::createStub(SystemConfigService::class), $pageLoader, $resolver, $contentRoute);
        $this->setRenderContainer($controller, $context);
        $request = new Request(attributes: [ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST => '["127.0.0.1","::1"]']);

        $response = $controller->renderSinglePage('landing-page-id', $request, $context);

        static::assertSame('127.0.0.1,::1', $response->headers->get('ct-maintenance-allowlist'));
    }

    private function setRenderContainer(MaintenanceController $controller, ?ChannelContext $context = null): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context ?? static::createStub(ChannelContext::class),
            RequestTransformer::FRONTEND_URL => 'http://localhost',
        ]);
        $stack = new RequestStack([$request]);
        $container = new ContainerBuilder();
        $container->set('request_stack', $stack);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $container->set('twig', static::createStub(Environment::class));
        $container->set(TemplateFinder::class, static::createStub(TemplateFinder::class));
        $container->set(SystemConfigService::class, static::createStub(SystemConfigService::class));
        $container->set(SeoUrlPlaceholderHandlerInterface::class, static::createStub(SeoUrlPlaceholderHandlerInterface::class));
        $container->set(MediaUrlPlaceholderHandlerInterface::class, static::createStub(MediaUrlPlaceholderHandlerInterface::class));
        $controller->setContainer($container);
    }
}

/**
 * @internal
 */
class MaintenanceControllerTestClass extends MaintenanceController
{
    use FrontendControllerMockTrait;
}

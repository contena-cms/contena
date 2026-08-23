<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\Exception\FrontendException;
use Contena\Frontend\Controller\FrontendController;
use Contena\Frontend\Event\FrontendRedirectEvent;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Tests\Unit\Frontend\Controller\fixtures\TestFrontendController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[CoversClass(FrontendController::class)]
class FrontendControllerTest extends TestCase
{
    private readonly TestFrontendController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestFrontendController();
    }

    public function testRenderFrontend(): void
    {
        $context = static::createStub(ChannelContext::class);
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
            RequestTransformer::FRONTEND_URL => 'foo',
        ]);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->once())->method('getCurrentRequest')->willReturn($request);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->willReturn('<html lang="en">test</html>');

        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer->expects($this->once())
            ->method('replace')
            ->with('<html lang="en">test</html>', 'foo', $context)
            ->willReturn('<html lang="en">test</html>');

        $mediaUrlHandler = static::createStub(MediaUrlPlaceholderHandlerInterface::class);
        $mediaUrlHandler->method('replace')->willReturnArgument(0);

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->expects($this->once())->method('find')->with('test.html.twig')->willReturn('test.html.twig');

        $container = new ContainerBuilder();
        $container->set('request_stack', $requestStack);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $container->set('twig', $twig);
        $container->set(TemplateFinder::class, $templateFinder);
        $container->set(SeoUrlPlaceholderHandlerInterface::class, $seoUrlReplacer);
        $container->set(MediaUrlPlaceholderHandlerInterface::class, $mediaUrlHandler);
        $container->set(SystemConfigService::class, static::createStub(SystemConfigService::class));
        $this->controller->setContainer($container);

        $response = $this->controller->testRenderFrontend('test.html.twig');

        static::assertSame('<html lang="en">test</html>', $response->getContent());
        static::assertSame('text/html', $response->headers->get('Content-Type'));
    }

    public function testRenderFrontendWithException(): void
    {
        $context = static::createStub(ChannelContext::class);
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
            RequestTransformer::FRONTEND_URL => 'foo',
        ]);

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())->method('render')->willThrowException(new SyntaxError('test'));

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->expects($this->once())->method('find')->with('test.html.twig')->willReturn('test.html.twig');

        $container = new ContainerBuilder();
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $container->set('request_stack', $requestStack);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $container->set('twig', $twig);
        $container->set(TemplateFinder::class, $templateFinder);
        $container->set(SystemConfigService::class, static::createStub(SystemConfigService::class));
        $this->controller->setContainer($container);

        $this->expectException(FrontendException::class);
        $this->controller->testRenderFrontend('test.html.twig');
    }

    public function testTrans(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects($this->once())->method('trans')->with('test', ['foo' => 'bar']);

        $container = new ContainerBuilder();
        $container->set('translator', $translator);
        $this->controller->setContainer($container);

        $this->controller->testTrans('test', ['foo' => 'bar']);
    }

    public function testCreateActionResponseWithRedirectTo(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/foo/generated');

        $request = new Request(['redirectTo' => 'foo', 'redirectParameters' => ['foo' => 'bar']]);
        $this->setRouterContainer($router);

        $response = $this->controller->testCreateActionResponse($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/foo/generated', $response->getTargetUrl());
    }

    public function testCreateActionResponseWithRedirectToRouteNotFoundException(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willThrowException(new RouteNotFoundException());

        $request = new Request(['redirectTo' => 'foo', 'redirectParameters' => ['foo' => 'bar']]);
        $this->setRouterContainer($router);

        $this->expectExceptionObject(FrontendException::routeNotFound('foo'));
        $this->controller->testCreateActionResponse($request);
    }

    public function testCreateActionResponseWithEmptyRedirectToWillRedirectToHomePage(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/');

        $this->setRouterContainer($router);
        $response = $this->controller->testCreateActionResponse(new Request(['redirectTo' => '', 'redirectParameters' => []]));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());
    }

    public function testCreateActionResponseWithArrayRedirectToWillRedirectToHomePage(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/');

        $this->setRouterContainer($router);
        $response = $this->controller->testCreateActionResponse(new Request([
            'redirectTo' => ['some', 'thing'],
            'redirectParameters' => [],
        ]));

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());
    }

    public function testCreateActionResponseWithForwardTo(): void
    {
        [$router, $request, $kernel, $requestStack] = $this->createForwardFixture();

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $container->set('request_stack', $requestStack);
        $container->set(RequestTransformerInterface::class, static::createStub(RequestTransformerInterface::class));
        $container->set('http_kernel', $kernel);
        $this->controller->setContainer($container);

        $response = $this->controller->testCreateActionResponse($request);

        static::assertNotInstanceOf(RedirectResponse::class, $response);
        static::assertSame('<html lang="en">test</html>', $response->getContent());
        static::assertSame('text/html', $response->headers->get('Content-Type'));
    }

    public function testCreateActionResponseWithForwardToRouteNotFoundException(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], Router::PATH_INFO)
            ->willThrowException(new RouteNotFoundException());

        $request = new Request(['forwardTo' => 'foo', 'forwardParameters' => ['foo' => 'bar']]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('request_stack', $requestStack);
        $container->set(RequestTransformerInterface::class, static::createStub(RequestTransformerInterface::class));
        $this->controller->setContainer($container);

        $this->expectExceptionObject(FrontendException::routeNotFound('foo'));
        $this->controller->testCreateActionResponse($request);
    }

    public function testCreateActionResponseWithNeitherRedirectNorForwardTo(): void
    {
        $response = $this->controller->testCreateActionResponse(new Request());

        static::assertNotInstanceOf(RedirectResponse::class, $response);
        static::assertSame('', $response->getContent());
    }

    public function testForwardToRoute(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('generate')->with('foo', ['foo' => 'bar'], Router::PATH_INFO)->willReturn('/foo/generated');
        $requestContext = static::createStub(RequestContext::class);
        $requestContext->method('getMethod')->willReturn('POST');
        $router->method('getContext')->willReturn($requestContext);
        $router->expects($this->once())->method('match')->with('/foo/generated')->willReturn(['_controller' => 'test_controller']);

        $request = new Request(['forwardTo' => 'foo', 'forwardParameters' => ['foo' => 'bar']]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer->expects($this->once())->method('extractInheritableAttributes')->with($request)->willReturn(['foo' => 'bar']);

        $kernel = $this->createMock(HttpKernel::class);
        $kernel->expects($this->once())->method('handle')->with(static::callback(static function (Request $forwardedRequest): bool {
            static::assertSame('bar', $forwardedRequest->attributes->get('foo'));
            static::assertSame('test_controller', $forwardedRequest->attributes->get('_controller'));
            static::assertSame(['foo' => 'bar'], $forwardedRequest->attributes->get('_route_params'));

            return true;
        }));

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('request_stack', $requestStack);
        $container->set(RequestTransformerInterface::class, $requestTransformer);
        $container->set('http_kernel', $kernel);
        $this->controller->setContainer($container);

        $this->controller->testForwardToRoute('foo', ['foo' => 'bar'], ['foo' => 'bar']);
    }

    public function testDecodeParamJson(): void
    {
        $params = $this->controller->testDecodeParam(new Request(['foobar' => '{"foo": "bar", "bar": "baz"}']), 'foobar');

        static::assertSame(['foo' => 'bar', 'bar' => 'baz'], $params);
    }

    public function testDecodeParamsEmpty(): void
    {
        static::assertSame([], $this->controller->testDecodeParam(new Request(), 'foo'));
    }

    public function testDecodeParamsNumeric(): void
    {
        static::assertSame([], $this->controller->testDecodeParam(new Request(['foobar' => 1]), 'foobar'));
    }

    public function testDecodeParamsArray(): void
    {
        static::assertSame(['bar' => 'baz'], $this->controller->testDecodeParam(new Request(['foo' => ['bar' => 'baz']]), 'foo'));
    }

    public function testRenderView(): void
    {
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder->expects($this->once())->method('find')->with('test.html.twig')->willReturn('frontend-view.html.twig');
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('frontend-view.html.twig', ['foo' => 'bar'])
            ->willReturn('<html lang="en">test</html>');

        $container = new ContainerBuilder();
        $container->set(TemplateFinder::class, $templateFinder);
        $container->set('twig', $twig);
        $this->controller->setContainer($container);

        static::assertSame('<html lang="en">test</html>', $this->controller->testRenderView('test.html.twig', ['foo' => 'bar']));
    }

    public function testRedirectEvent(): void
    {
        $event = new FrontendRedirectEvent('test_route', ['test' => 'param']);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->with(static::equalTo($event));
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('generate')->with('test_route', ['test' => 'param'])->willReturn('http://localhost/test_route');

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', $dispatcher);
        $container->set('router', $router);
        $this->controller->setContainer($container);

        $this->controller->testRedirectToRoute('test_route', ['test' => 'param']);
    }

    private function setRouterContainer(RouterInterface $router): void
    {
        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', static::createStub(EventDispatcherInterface::class));
        $this->controller->setContainer($container);
    }

    /**
     * @return array{RouterInterface, Request, HttpKernel, RequestStack}
     */
    private function createForwardFixture(): array
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('generate')->with('foo', ['foo' => 'bar'], Router::PATH_INFO)->willReturn('/foo/generated');
        $requestContext = static::createStub(RequestContext::class);
        $requestContext->method('getMethod')->willReturn('POST');
        $router->method('getContext')->willReturn($requestContext);
        $router->method('match')->willReturn(['_controller' => 'test_controller']);

        $request = new Request(['forwardTo' => 'foo', 'forwardParameters' => ['foo' => 'bar']]);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $controllerResolver = static::createStub(ControllerResolverInterface::class);
        $controllerResolver->method('getController')->willReturn(
            static fn () => new Response('<html lang="en">test</html>', Response::HTTP_PERMANENTLY_REDIRECT, ['Content-Type' => 'text/html'])
        );

        return [
            $router,
            $request,
            new HttpKernel(static::createStub(EventDispatcherInterface::class), $controllerResolver, $requestStack),
            $requestStack,
        ];
    }
}

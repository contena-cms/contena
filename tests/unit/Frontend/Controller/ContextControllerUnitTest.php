<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Frontend\Controller\ContextController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ContextController::class)]
class ContextControllerUnitTest extends TestCase
{
    public function testSwitchLanguageRequiresLanguageId(): void
    {
        $controller = $this->createController();

        static::expectExceptionObject(RoutingException::missingRequestParameter('languageId'));
        $controller->switchLanguage(new Request(), static::createStub(ChannelContext::class));
    }

    public function testSwitchLanguageRejectsNonStringLanguageId(): void
    {
        $controller = $this->createController();

        static::expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));
        $controller->switchLanguage(new Request([], ['languageId' => 1]), static::createStub(ChannelContext::class));
    }

    public function testSwitchLanguageRejectsInvalidUuid(): void
    {
        $controller = $this->createController();

        static::expectExceptionObject(RoutingException::invalidRequestParameter('languageId'));
        $controller->switchLanguage(new Request([], ['languageId' => 'not-a-uuid']), static::createStub(ChannelContext::class));
    }

    public function testSwitchLanguageThrowsWhenLanguageDoesNotExist(): void
    {
        $route = $this->createMock(AbstractContextSwitchRoute::class);
        $route->expects($this->once())->method('switchContext')->willThrowException(
            new ConstraintViolationException(new ConstraintViolationList(), [])
        );
        $controller = $this->createController($route);
        $languageId = Uuid::randomHex();

        static::expectExceptionObject(RoutingException::languageNotFound($languageId));
        $controller->switchLanguage(new Request([], ['languageId' => $languageId]), static::createStub(ChannelContext::class));
    }

    public function testSwitchLanguageUsesReturnedChannelDomainAndDefaultRoute(): void
    {
        $route = static::createStub(AbstractContextSwitchRoute::class);
        $route->method('switchContext')->willReturn(new ContextTokenResponse(Uuid::randomHex(), 'http://localhost'));

        $routerContext = new RequestContext();
        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())->method('generate')->willReturn('http://localhost');
        $router->expects($this->once())->method('getContext')->willReturn($routerContext);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $controller = $this->createController($route, $requestStack, $router);
        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => null]),
            static::createStub(ChannelContext::class),
        );

        static::assertSame('localhost', $routerContext->getHost());
    }

    public function testSwitchLanguageFallsBackToHomeForUnknownRedirectTarget(): void
    {
        $route = static::createStub(AbstractContextSwitchRoute::class);
        $route->method('switchContext')->willReturn(new ContextTokenResponse(Uuid::randomHex(), 'http://localhost'));

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->exactly(2))->method('generate')->willReturnCallback(
            static function (string $routeName): string {
                if ($routeName === 'frontend.invalid.page') {
                    throw new RouteNotFoundException();
                }

                return 'http://localhost';
            },
        );
        $router->expects($this->once())->method('getContext')->willReturn(new RequestContext());

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $controller = $this->createController($route, $requestStack, $router);
        $controller->switchLanguage(
            new Request([], ['languageId' => Defaults::LANGUAGE_SYSTEM, 'redirectTo' => 'frontend.invalid.page']),
            static::createStub(ChannelContext::class),
        );
    }

    public function testSwitchLanguagePreservesLocaleForValidRedirectTarget(): void
    {
        $route = static::createStub(AbstractContextSwitchRoute::class);
        $route->method('switchContext')->willReturn(new ContextTokenResponse(Uuid::randomHex(), 'http://localhost'));

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->exactly(2))->method('generate')->willReturn('http://localhost');
        $router->expects($this->once())->method('getContext')->willReturn(new RequestContext());

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->expects($this->exactly(2))->method('getMainRequest')->willReturn(new Request());

        $controller = $this->createController($route, $requestStack, $router);
        $controller->switchLanguage(
            new Request([], [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'languageCode_' . Defaults::LANGUAGE_SYSTEM => 'zh-CN',
                'redirectTo' => 'frontend.home.page',
                'redirectParameters' => '{"foo":"bar"}',
            ]),
            static::createStub(ChannelContext::class),
        );
    }

    private function createController(
        ?AbstractContextSwitchRoute $route = null,
        ?RequestStack $requestStack = null,
        ?RouterInterface $router = null,
    ): ContextController {
        return new ContextController(
            $route ?? static::createStub(AbstractContextSwitchRoute::class),
            $requestStack ?? static::createStub(RequestStack::class),
            $router ?? static::createStub(RouterInterface::class),
        );
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Framework\Gateway\Context\Channel\AbstractContextGatewayRoute;
use Contena\Core\Framework\Gateway\GatewayException;
use Contena\Core\Framework\Test\TestSessionStorage;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\ContextGatewayController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
#[CoversClass(ContextGatewayController::class)]
class ContextGatewayControllerTest extends TestCase
{
    public function testGateway(): void
    {
        $context = Generator::generateChannelContext(token: 'hatoken');
        $cart = new Cart('hatoken');
        $request = new Request(request: ['foo' => 'bar', 'bat' => 'baz']);
        $expectedResponse = new ContextTokenResponse('newHatoken');

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with('hatoken', $context)
            ->willReturn($cart);

        $route = $this->createMock(AbstractContextGatewayRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $context)
            ->willReturn($expectedResponse);

        $container = $this->createStubContainerWithFlashBag();

        $controller = new ContextGatewayController($route, $cartService);
        $controller->setContainer($container);

        $newResponse = $controller->gateway($request, $context);

        static::assertInstanceOf(ContextTokenResponse::class, $newResponse);
        static::assertSame('newHatoken', $newResponse->getToken());
    }

    public function testGatewayWithGenericException(): void
    {
        $context = Generator::generateChannelContext(token: 'hatoken');
        $cart = new Cart('hatoken');
        $request = new Request(request: ['foo' => 'bar', 'bat' => 'baz']);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with('hatoken', $context)
            ->willReturn($cart);

        $route = $this->createMock(AbstractContextGatewayRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $context)
            ->willThrowException(GatewayException::emptyAppResponse('FOO'));

        $container = $this->createStubContainerWithFlashBag();

        $controller = new ContextGatewayController($route, $cartService);
        $controller->setContainer($container);

        $newResponse = $controller->gateway($request, $context);

        static::assertInstanceOf(JsonResponse::class, $newResponse);
        static::assertSame(Response::HTTP_BAD_REQUEST, $newResponse->getStatusCode());

        $session = $container->get('request_stack')->getSession();

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        $flashBag = $session->getFlashBag();
        $errors = $flashBag->get('danger');

        static::assertCount(0, $errors);
    }

    public function testGatewayWithUserException(): void
    {
        $context = Generator::generateChannelContext(token: 'hatoken');
        $cart = new Cart('hatoken');
        $request = new Request(request: ['foo' => 'bar', 'bat' => 'baz']);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with('hatoken', $context)
            ->willReturn($cart);

        $route = $this->createMock(AbstractContextGatewayRoute::class);
        $route
            ->expects($this->once())
            ->method('load')
            ->with($request, $cart, $context)
            ->willThrowException(GatewayException::userMessage('FOO'));

        $container = $this->createStubContainerWithFlashBag();

        $controller = new ContextGatewayController($route, $cartService);
        $controller->setContainer($container);

        $newResponse = $controller->gateway($request, $context);

        static::assertInstanceOf(JsonResponse::class, $newResponse);
        static::assertSame(Response::HTTP_BAD_REQUEST, $newResponse->getStatusCode());

        $session = $container->get('request_stack')->getSession();

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);

        $flashBag = $session->getFlashBag();
        $errors = $flashBag->get('danger');

        static::assertCount(1, $errors);

        $error = $errors[0];

        static::assertSame('FOO', $error);
    }

    private function createStubContainerWithFlashBag(): ContainerInterface
    {
        $storage = new TestSessionStorage();
        $storage->clear(); // ensure a clean state since storage is a stub shared between tests

        $request = new Request();
        $request->setSession(new Session($storage));

        $requestStack = new RequestStack([$request]);

        $container = static::createStub(ContainerInterface::class);
        $container
            ->method('get')
            ->willReturn($requestStack);

        return $container;
    }
}

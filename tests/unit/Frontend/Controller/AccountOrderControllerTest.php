<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\Channel\AbstractOrderRoute;
use Contena\Core\Checkout\Order\Channel\OrderRouteResponse;
use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Payment\Channel\AbstractHandlePaymentMethodRoute;
use Contena\Core\Checkout\Payment\Channel\HandlePaymentMethodRouteResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Currency\CurrencyEntity;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Controller\AccountOrderController;
use Contena\Frontend\Page\Account\Order\AccountEditOrderPageLoader;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AccountOrderController::class)]
class AccountOrderControllerTest extends TestCase
{

    private Stub&AbstractOrderRoute $orderRouteMock;

    private Stub&AccountEditOrderPageLoader $accountEditOrderPageLoaderMock;

    private Stub&AbstractHandlePaymentMethodRoute $handlePaymentRouteMock;

    private Stub&OrderService $orderServiceMock;

    protected function setUp(): void
    {
        $this->orderRouteMock = static::createStub(AbstractOrderRoute::class);
        $this->accountEditOrderPageLoaderMock = static::createStub(AccountEditOrderPageLoader::class);
        $this->handlePaymentRouteMock = static::createStub(AbstractHandlePaymentMethodRoute::class);

        $this->orderServiceMock = static::createStub(OrderService::class);

        $this->controller = $this->createController(
            $this->orderRouteMock,
            $this->handlePaymentRouteMock,
        );
    }

    public function testEditOrderNotFound(): void
    {
        $ids = new IdsCollection();

        $response = $this->controller->editOrder($ids->get('order'), new Request(), Generator::generateChannelContext());

        // Ensure flash massage is shown
        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_NOT_FOUND']], $this->controller->recorder()->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testEditOrderInvalidUuid(): void
    {
        // Ensure invalid uuid exception is thrown
        $this->orderRouteMock->method('load')->willThrowException(new InvalidUuidException('invalid-id'));

        $response = $this->controller->editOrder('invalid-id', new Request(), Generator::generateChannelContext());

        // Ensure flash massage is shown
        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_NOT_FOUND']], $this->controller->recorder()->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testOrderAlreadyPaid(): void
    {
        $ids = new IdsCollection();

        $channelContext = Generator::generateChannelContext();
        $channelContext->assign([
            'currency' => (new CurrencyEntity())->assign([
                'id' => $ids->get('currency'),
            ]),
        ]);

        $order = (new OrderEntity())->assign([
            '_uniqueIdentifier' => Uuid::randomHex(),
            'currencyId' => $ids->get('currency'),
            'deliveries' => new OrderDeliveryCollection(),
        ]);
        $orders = new OrderCollection([$order]);

        $accountRouteResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', $dispatcher);

        $this->controller->setContainer($container);

        $this->orderRouteMock->method('load')->willReturn($accountRouteResponse);
        $this->accountEditOrderPageLoaderMock->method('load')->willThrowException(OrderException::orderAlreadyPaid($ids->get('order')));

        $response = $this->controller->editOrder($ids->get('order'), new Request(), $channelContext);

        // Ensure flash massage is shown
        static::assertSame(['danger' => ['error.CHECKOUT__ORDER_ORDER_ALREADY_PAID']], $this->controller->recorder()->flashBag);
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testCancelOrderRedirectsToCorrectRouteForLoggedInUser(): void
    {
        $channelContextMock = static::createStub(ChannelContext::class);

        $user = new UserEntity();
        $user->setGuest(false);
        $channelContextMock->method('getUser')->willReturn($user);

        $request = new Request();
        $request->attributes->set('orderId', Uuid::randomHex());

        $response = $this->controller->cancelOrder($request, $channelContextMock);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.order.page', $response->getTargetUrl());
    }

    public function testCancelOrderRedirectsToCorrectRouteForGuestUser(): void
    {
        $channelContextMock = static::createStub(ChannelContext::class);

        $user = new UserEntity();
        $user->setGuest(true);
        $channelContextMock->method('getUser')->willReturn($user);

        $request = new Request();
        $request->attributes->set('orderId', Uuid::randomHex());
        $request->attributes->set('deepLinkCode', 'deep-link-code');

        $response = $this->controller->cancelOrder($request, $channelContextMock);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.order.single.page', $response->getTargetUrl());
    }

    public function testOrderChangePaymentPassesTheSelectedPaymentMethodToTheEditOrderPage(): void
    {
        $ids = new IdsCollection();

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->never())
            ->method('switchContext');

        $controller = $this->createController(
            $this->orderRouteMock,
            $this->handlePaymentRouteMock,
            $contextSwitchRoute,
        );

        $request = new Request();
        $request->request->set('paymentMethodId', $ids->get('payment-method'));

        $response = $controller->orderChangePayment($ids->get('order'), $request, Generator::generateChannelContext());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.edit-order.page', $response->getTargetUrl());
        static::assertSame(
            [[
                'parameters' => [
                    'orderId' => $ids->get('order'),
                    'paymentMethodId' => $ids->get('payment-method'),
                ],
                'status' => Response::HTTP_FOUND,
            ]],
            $controller->recorder()->redirected['frontend.account.edit-order.page']
        );
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testOrderChangePaymentStillSwitchesTheContext(): void
    {
        $ids = new IdsCollection();
        $channelContext = Generator::generateChannelContext();

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with(
                new RequestDataBag([ChannelContextService::PAYMENT_METHOD_ID => $ids->get('payment-method')]),
                $channelContext
            );

        $controller = $this->createController(
            $this->orderRouteMock,
            $this->handlePaymentRouteMock,
            $contextSwitchRoute,
        );

        $request = new Request();
        $request->request->set('paymentMethodId', $ids->get('payment-method'));

        $controller->orderChangePayment($ids->get('order'), $request, $channelContext);
    }

    public function testTransactionsStateMachineAssociationIsLoadedOnOrderUpdate(): void
    {
        $ids = new IdsCollection();

        $channelContext = Generator::generateChannelContext();
        $channelContext->assign([
            'currency' => (new CurrencyEntity())->assign([
                'id' => $ids->get('currency'),
            ]),
        ]);

        $criteria = new Criteria([$ids->get('order')]);
        $criteria->addAssociation('transactions.stateMachineState');

        $stateMachineState = new StateMachineStateEntity();
        $stateMachineState->setTechnicalName(OrderTransactionStates::STATE_CANCELLED);

        $transaction = new OrderTransactionEntity();
        $transaction->setId($ids->get('transaction'));
        $transaction->setStateMachineState($stateMachineState);

        // Mock the OrderEntity with transactions
        $order = new OrderEntity();
        $order->setId($ids->get('order'));
        $order->setCurrencyId($ids->get('currency'));
        $order->setDeliveries(new OrderDeliveryCollection());
        $order->setTransactions(new OrderTransactionCollection([$transaction]));

        $orders = new OrderCollection([$order]);

        $accountRouteResponse = new OrderRouteResponse(
            new EntitySearchResult(
                OrderDefinition::ENTITY_NAME,
                1,
                $orders,
                null,
                $criteria,
                $channelContext->getContext()
            )
        );

        $orderRoute = $this->createMock(AbstractOrderRoute::class);
        $orderRoute
            ->expects($this->once())
            ->method('load')
            ->with($request = new Request(), $channelContext, $criteria)
            ->willReturn($accountRouteResponse);

        $this->orderServiceMock
            ->method('isPaymentChangeableByTransactionState')
            ->willReturn(true);

        $handlePaymentRoute = $this->createMock(AbstractHandlePaymentMethodRoute::class);
        $handlePaymentRoute
            ->expects($this->once())
            ->method('load')
            ->with(static::isInstanceOf(Request::class), $channelContext)
            ->willReturn(new HandlePaymentMethodRouteResponse(new RedirectResponse('https://doesnotexist.com')));

        $controller = $this->createController($orderRoute, $handlePaymentRoute);

        $controller->updateOrder($ids->get('order'), $request, $channelContext);
    }

    /**
     * @param array<string, string> $credentials
     */
    #[DataProvider('guestAuthenticationFailures')]
    public function testOrderSingleOverviewRedirectsToGuestLogin(\Throwable $exception, array $credentials, bool $expectedLoginError): void
    {
        $orderPageLoader = static::createStub(AccountOrderPageLoader::class);
        $orderPageLoader->method('load')->willThrowException($exception);

        $controller = $this->createController(
            $this->orderRouteMock,
            $this->handlePaymentRouteMock,
            orderPageLoader: $orderPageLoader,
        );

        $request = new Request(request: $credentials, attributes: ['deepLinkCode' => 'deep-link-code']);

        $response = $controller->orderSingleOverview($request, Generator::generateChannelContext());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('frontend.account.guest.login.page', $response->getTargetUrl());

        $parameters = $controller->recorder()->redirected['frontend.account.guest.login.page'][0]['parameters'];
        static::assertSame(['deepLinkCode' => 'deep-link-code'], $parameters['redirectParameters']);
        static::assertSame($expectedLoginError, $parameters['loginError']);
    }

    public static function guestAuthenticationFailures(): \Generator
    {
        yield 'opening the link without credentials asks for them without an error' => [
            OrderException::guestNotAuthenticated(),
            [],
            false,
        ];

        yield 'submitted credentials for a code matching no order show an error' => [
            OrderException::guestNotAuthenticated(),
            ['email' => 'guest@example.com', 'zipcode' => '12345'],
            true,
        ];

        yield 'an incomplete submission asks for the credentials again without an error' => [
            OrderException::guestNotAuthenticated(),
            ['email' => 'guest@example.com'],
            false,
        ];

        yield 'submitted credentials not matching the order show an error' => [
            OrderException::wrongGuestCredentials(),
            ['email' => 'guest@example.com', 'zipcode' => '12345'],
            true,
        ];

        yield 'throttled submissions only show the wait time, not the error' => [
            OrderException::userAuthThrottledException(10),
            ['email' => 'guest@example.com', 'zipcode' => '12345'],
            false,
        ];
    }

}

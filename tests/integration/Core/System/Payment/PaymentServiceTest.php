<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1786016192ContenaBasicData;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\Event\PaymentGatewayCallCompletedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayCallFailedEvent;
use Contena\Core\System\Payment\Event\PaymentGatewayCallStartedEvent;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Gateway\SubscribeHandlerInterface;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Service\PaymentOrderService;
use Contena\Core\System\Payment\Service\PaymentRefundService;
use Contena\Core\System\Payment\Service\PaymentService;
use Contena\Core\System\Payment\Service\PaymentSubscriptionService;
use Contena\Core\System\Payment\Service\PaymentTransferService;
use Contena\Core\System\Payment\Struct\PaymentRequest;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
final class PaymentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PaymentAppEntity $app;

    private Context $context;

    /**
     * @var list<object>
     */
    private array $events;

    private WorkflowGateway $gateway;

    private PaymentService $paymentService;

    private WorkflowRouteResolver $routeResolver;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $connection = static::getContainer()->get(Connection::class);
        new Migration1786016192ContenaBasicData()->update($connection);

        $suffix = \bin2hex(\random_bytes(8));
        $appId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $channelConfigId = Uuid::randomHex();
        $channelCode = 'workflow-' . $suffix;

        $this->repository('payment_app')->create([[
            'id' => $appId,
            'appCode' => 'workflow-app-' . $suffix,
            'appSecret' => 'test-secret',
            'name' => 'Payment workflow test',
            'status' => true,
        ]], $this->context);
        $this->repository('payment_channel')->create([[
            'id' => $channelId,
            'code' => $channelCode,
            'name' => 'Payment workflow gateway',
            'status' => true,
            'sort' => 1,
        ]], $this->context);
        $this->repository('payment_channel_config')->create([[
            'id' => $channelConfigId,
            'paymentAppId' => $appId,
            'channelId' => $channelId,
            'config' => ['merchantId' => 'merchant-1'],
            'status' => true,
        ]], $this->context);

        $app = $this->repository('payment_app')->search(new Criteria([$appId]), $this->context)->getEntities()->first();
        static::assertInstanceOf(PaymentAppEntity::class, $app);
        $this->app = $app;
        $this->gateway = new WorkflowGateway($channelCode);
        $this->routeResolver = new WorkflowRouteResolver(new PaymentRoute(
            $this->gateway,
            $channelConfigId,
            ['merchantId' => 'merchant-1'],
            false,
        ));

        $this->events = [];
        $eventDispatcher = new EventDispatcher();
        foreach ([PaymentGatewayCallStartedEvent::class, PaymentGatewayCallCompletedEvent::class, PaymentGatewayCallFailedEvent::class] as $eventClass) {
            $eventDispatcher->addListener($eventClass, function (object $event): void {
                $this->events[] = $event;
            });
        }

        $numberRange = static::getContainer()->get(AbstractNumberRangeValueGenerator::class);
        $stateMachine = static::getContainer()->get(StateMachineRegistry::class);
        $clock = new MockClock('2026-09-03T10:00:00+08:00');
        /** @var EntityRepository<PaymentOrderCollection> $paymentOrderRepository */
        $paymentOrderRepository = $this->repository('payment_order');
        /** @var EntityRepository<PaymentOrderTransactionCollection> $paymentOrderTransactionRepository */
        $paymentOrderTransactionRepository = $this->repository('payment_order_transaction');
        /** @var EntityRepository<PaymentRefundCollection> $paymentRefundRepository */
        $paymentRefundRepository = $this->repository('payment_refund');
        /** @var EntityRepository<PaymentTransferCollection> $paymentTransferRepository */
        $paymentTransferRepository = $this->repository('payment_transfer');
        /** @var EntityRepository<PaymentRecurringCollection> $paymentRecurringRepository */
        $paymentRecurringRepository = $this->repository('payment_recurring');
        $orderService = new PaymentOrderService(
            $paymentOrderRepository,
            $paymentOrderTransactionRepository,
            $numberRange,
            $stateMachine,
            $this->routeResolver,
            $eventDispatcher,
            $connection,
            $clock,
        );
        $refundService = new PaymentRefundService(
            $paymentRefundRepository,
            $orderService,
            $numberRange,
            $this->routeResolver,
            $eventDispatcher,
            $connection,
            $clock,
        );
        $transferService = new PaymentTransferService(
            $paymentTransferRepository,
            $numberRange,
            $stateMachine,
            $this->routeResolver,
            $eventDispatcher,
            $connection,
            $clock,
        );
        $subscriptionService = new PaymentSubscriptionService(
            $paymentRecurringRepository,
            $numberRange,
            $this->routeResolver,
            $eventDispatcher,
            $clock,
        );
        $this->paymentService = new PaymentService($orderService, $refundService, $transferService, $subscriptionService);
    }

    public function testPaymentIsIdempotentAndQueryReusesItsChannelConfiguration(): void
    {
        $this->gateway->paymentResult = new PaymentResult(
            PaymentStatus::SUCCEEDED,
            PaymentResult::ACTION_REDIRECT,
            'https://pay.example/checkout',
            'provider-request-1',
            'provider-trade-1',
        );
        $request = new PaymentRequest(
            'app-order-1',
            1250,
            'cny',
            'h5',
            'Order 1',
            returnUrl: 'https://app.example/return',
        );

        $created = $this->paymentService->pay($this->app, $request, $this->context);
        $repeated = $this->paymentService->pay($this->app, $request, $this->context);

        static::assertSame(1, $this->gateway->paymentCalls);
        static::assertSame($created->resourceNo, $this->gateway->lastOrder?->orderNo);
        static::assertSame($created->resourceNo, $repeated->resourceNo);
        static::assertSame('app-order-1', $created->externalResourceNo);
        static::assertNotSame($created->externalResourceNo, $created->resourceNo);
        static::assertCount(2, $this->events);

        $order = $this->loadOrder('app-order-1');
        static::assertSame($this->routeResolver->route->channelConfigId, $order->channelConfigId);
        static::assertSame('https://app.example/return', $order->returnUrl);
        static::assertSame('provider-trade-1', $order->channelTradeNo);
        static::assertEquals($this->gateway->paymentResult->toArray(), $order->primaryTransaction?->responseData);

        $this->gateway->queryResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-trade-1');
        $queryResult = $this->paymentService->query($this->app, new QueryRequest(externalOrderNo: 'app-order-1'), $this->context);

        static::assertSame($created->resourceNo, $queryResult->resourceNo);
        static::assertSame(1, $this->gateway->queryCalls);
        static::assertSame([[
            'channel' => $this->gateway->code(),
            'channelConfigId' => $this->routeResolver->route->channelConfigId,
            'operation' => 'query',
        ]], $this->routeResolver->configuredCalls);
        static::assertCount(4, $this->events);
    }

    public function testRefundReservationIsReleasedOnlyForAConfirmedFailure(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-trade-2');
        $this->paymentService->pay($this->app, new PaymentRequest('app-order-2', 1000, 'CNY', 'h5', 'Order 2'), $this->context);

        $this->gateway->refundResult = new PaymentResult(PaymentStatus::FAILED, resultCode: 'REFUSED');
        $failed = $this->paymentService->refund($this->app, new RefundRequest('app-refund-1', 400, externalOrderNo: 'app-order-2'), $this->context);

        static::assertSame(PaymentStatus::FAILED, $failed->status);
        static::assertSame(0, $this->loadOrder('app-order-2')->refundedAmount);

        $this->gateway->refundException = new \RuntimeException('Provider connection interrupted');
        try {
            $this->paymentService->refund($this->app, new RefundRequest('app-refund-2', 600, externalOrderNo: 'app-order-2'), $this->context);
            static::fail('Expected the provider exception to be rethrown.');
        } catch (\RuntimeException $exception) {
            static::assertSame('Provider connection interrupted', $exception->getMessage());
        }

        static::assertSame(600, $this->loadOrder('app-order-2')->refundedAmount);
        static::assertSame(2, $this->gateway->refundCalls);
        $lastEventKey = array_key_last($this->events);
        static::assertNotNull($lastEventKey);
        static::assertInstanceOf(PaymentGatewayCallFailedEvent::class, $this->events[$lastEventKey]);
    }

    public function testPaymentRejectsDisabledApps(): void
    {
        $disabledApp = clone $this->app;
        $disabledApp->assign(['status' => false]);

        $this->expectExceptionObject(PaymentException::appNotFound($disabledApp->appCode));

        $this->paymentService->pay($disabledApp, new PaymentRequest('disabled-app-order', 100, 'CNY', 'h5', 'Disabled app'), $this->context);
    }

    public function testPaymentRejectsGlobalWriteContext(): void
    {
        $this->expectExceptionObject(PaymentException::invalidRequest('Payment writes require a platform or tenant context.'));

        $this->paymentService->pay($this->app, new PaymentRequest('global-order', 100, 'CNY', 'h5', 'Global context'), Context::createGlobalContext());
    }

    public function testTransferAndSubscriptionPersistTheirOwnResults(): void
    {
        $this->gateway->transferResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-transfer-1', resultCode: 'SUCCESS');
        $transferRequest = new TransferRequest('app-transfer-1', 800, 'cny', 'payee-1', 'Payee', remark: 'Payout');
        $transfer = $this->paymentService->transfer($this->app, $transferRequest, $this->context);
        $repeatedTransfer = $this->paymentService->transfer($this->app, $transferRequest, $this->context);

        static::assertSame(1, $this->gateway->transferCalls);
        static::assertSame($transfer->resourceNo, $this->gateway->lastTransfer?->transferNo);
        static::assertSame($transfer->resourceNo, $repeatedTransfer->resourceNo);
        $storedTransfer = $this->findByExternalReference('payment_transfer', 'externalTransferNo', 'app-transfer-1');
        static::assertInstanceOf(PaymentTransferEntity::class, $storedTransfer);
        static::assertSame('provider-transfer-1', $storedTransfer->channelOrderId);
        static::assertEquals($this->gateway->transferResult->toArray(), $storedTransfer->responseData);

        $this->gateway->subscriptionResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-agreement-1');
        $subscriptionRequest = new SubscriptionRequest('app-agreement-1', periodType: 'MONTH', period: 1, singleAmount: 500);
        $subscription = $this->paymentService->subscribe($this->app, $subscriptionRequest, $this->context);
        $repeatedSubscription = $this->paymentService->subscribe($this->app, $subscriptionRequest, $this->context);

        static::assertSame(1, $this->gateway->subscriptionCalls);
        static::assertSame($subscription->resourceNo, $this->gateway->lastSubscription?->recurringNo);
        static::assertSame($subscription->resourceNo, $repeatedSubscription->resourceNo);
        $storedSubscription = $this->findByExternalReference('payment_recurring', 'externalRecurringNo', 'app-agreement-1');
        static::assertInstanceOf(PaymentRecurringEntity::class, $storedSubscription);
        static::assertSame('provider-agreement-1', $storedSubscription->channelRecurringNo);
        static::assertEquals($this->gateway->subscriptionResult->toArray(), $storedSubscription->responseData);
    }

    private function loadOrder(string $externalOrderNo): PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('externalOrderNo', $externalOrderNo));
        $criteria->addAssociation('state');
        $criteria->addAssociation('primaryTransaction');
        $order = $this->repository('payment_order')->search($criteria, $this->context)->getEntities()->first();

        static::assertInstanceOf(PaymentOrderEntity::class, $order);

        return $order;
    }

    private function findByExternalReference(string $entity, string $field, string $value): ?object
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter($field, $value));

        return $this->repository($entity)->search($criteria, $this->context)->getEntities()->first();
    }

    /**
     * @return EntityRepository<EntityCollection<Entity>>
     */
    private function repository(string $entity): EntityRepository
    {
        $repository = static::getContainer()->get($entity . '.repository');
        static::assertInstanceOf(EntityRepository::class, $repository);

        return $repository;
    }
}

/**
 * @internal
 */
final class WorkflowRouteResolver extends AbstractPaymentRouteResolver
{
    /**
     * @var list<array{channel: string, channelConfigId: string, operation: string}>
     */
    public array $configuredCalls = [];

    public function __construct(public readonly PaymentRoute $route)
    {
    }

    public function getDecorated(): AbstractPaymentRouteResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolve(string $appId, string $operation, Context $context, ?string $method = null, ?string $preferredChannel = null): PaymentRoute
    {
        return $this->route;
    }

    public function resolveConfigured(string $channel, string $channelConfigId, string $operation, Context $context): PaymentRoute
    {
        $this->configuredCalls[] = \compact('channel', 'channelConfigId', 'operation');

        return $this->route;
    }
}

/**
 * @internal
 */
final class WorkflowGateway implements PaymentHandlerInterface, QueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface, SubscribeHandlerInterface
{
    public ?PaymentOrderEntity $lastOrder = null;

    public ?PaymentRecurringEntity $lastSubscription = null;

    public ?PaymentTransferEntity $lastTransfer = null;

    public int $paymentCalls = 0;

    public PaymentResult $paymentResult;

    public int $queryCalls = 0;

    public PaymentResult $queryResult;

    public int $refundCalls = 0;

    public ?\Throwable $refundException = null;

    public PaymentResult $refundResult;

    public int $subscriptionCalls = 0;

    public PaymentResult $subscriptionResult;

    public int $transferCalls = 0;

    public PaymentResult $transferResult;

    public function __construct(private readonly string $gatewayCode)
    {
        $this->paymentResult = new PaymentResult(PaymentStatus::PENDING);
        $this->queryResult = new PaymentResult(PaymentStatus::PENDING);
        $this->refundResult = new PaymentResult(PaymentStatus::PROCESSING);
        $this->transferResult = new PaymentResult(PaymentStatus::PROCESSING);
        $this->subscriptionResult = new PaymentResult(PaymentStatus::PENDING);
    }

    public function code(): string
    {
        return $this->gatewayCode;
    }

    public function pay(PaymentOrderEntity $order, array $config): PaymentResult
    {
        ++$this->paymentCalls;
        $this->lastOrder = $order;

        return $this->paymentResult;
    }

    public function query(PaymentOrderEntity $order, array $config): PaymentResult
    {
        ++$this->queryCalls;
        $this->lastOrder = $order;

        return $this->queryResult;
    }

    public function refund(PaymentRefundEntity $refund, PaymentOrderEntity $order, array $config): PaymentResult
    {
        ++$this->refundCalls;
        if ($this->refundException instanceof \Throwable) {
            throw $this->refundException;
        }

        return $this->refundResult;
    }

    public function transfer(PaymentTransferEntity $transfer, array $config): PaymentResult
    {
        ++$this->transferCalls;
        $this->lastTransfer = $transfer;

        return $this->transferResult;
    }

    public function subscribe(PaymentRecurringEntity $subscription, array $config): PaymentResult
    {
        ++$this->subscriptionCalls;
        $this->lastSubscription = $subscription;

        return $this->subscriptionResult;
    }
}

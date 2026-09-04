<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_8\Migration1786016192ContenaBasicData;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentNotificationTypes;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentTransactionStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderStates;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundStatus;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferCollection;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferEntity;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferStates;
use Contena\Core\System\Payment\Gateway\GatewayNotificationHandlerInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\Gateway\PaymentStatus;
use Contena\Core\System\Payment\Gateway\QueryHandlerInterface;
use Contena\Core\System\Payment\Gateway\RefundHandlerInterface;
use Contena\Core\System\Payment\Gateway\SubscribeHandlerInterface;
use Contena\Core\System\Payment\Gateway\TransferHandlerInterface;
use Contena\Core\System\Payment\OpenApi\Api\PaymentRequest;
use Contena\Core\System\Payment\Order\PaymentOrderPersister;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Refund\PaymentRefundService;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Service\GatewayNotificationService;
use Contena\Core\System\Payment\Service\PaymentNotificationTargetResolver;
use Contena\Core\System\Payment\Service\PaymentOrderService;
use Contena\Core\System\Payment\Service\PaymentOrderStateHandler;
use Contena\Core\System\Payment\Service\PaymentService;
use Contena\Core\System\Payment\Service\PaymentTransferService;
use Contena\Core\System\Payment\Struct\GatewayNotification;
use Contena\Core\System\Payment\Struct\GatewayNotificationResult;
use Contena\Core\System\Payment\Struct\PaymentResult;
use Contena\Core\System\Payment\Struct\PaymentRoute;
use Contena\Core\System\Payment\Struct\QueryRequest;
use Contena\Core\System\Payment\Struct\RefundRequest;
use Contena\Core\System\Payment\Struct\SubscriptionRequest;
use Contena\Core\System\Payment\Struct\TransferRequest;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionService;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
final class PaymentServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private PaymentAppEntity $app;

    private Context $context;

    private string $channelConfigId;

    private string $channelCode;

    private string $channelId;

    private WorkflowGateway $gateway;

    private PaymentService $paymentService;

    private GatewayNotificationService $notificationService;

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
        $this->channelConfigId = $channelConfigId;
        $this->channelCode = $channelCode;
        $this->channelId = $channelId;

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
        /** @var EntityRepository<PaymentChannelConfigCollection> $paymentChannelConfigRepository */
        $paymentChannelConfigRepository = $this->repository('payment_channel_config');
        /** @var EntityRepository<PaymentChannelNotifyRecordCollection> $paymentChannelNotifyRecordRepository */
        $paymentChannelNotifyRecordRepository = $this->repository('payment_channel_notify_record');
        /** @var EntityRepository<PaymentNotifyRecordCollection> $paymentNotifyRecordRepository */
        $paymentNotifyRecordRepository = $this->repository('payment_notify_record');
        $gatewayResolver = new PaymentGatewayResolver($paymentChannelConfigRepository, new GatewayRegistry([$this->gateway]));
        $orderService = new PaymentOrderService(
            $paymentOrderRepository,
            $paymentOrderTransactionRepository,
            static::getContainer()->get(PaymentOrderPersister::class),
            static::getContainer()->get(PaymentOrderStateHandler::class),
            $this->routeResolver,
            $gatewayResolver,
            $connection,
            $clock,
        );
        $refundService = new PaymentRefundService(
            $paymentRefundRepository,
            $orderService,
            $numberRange,
            $gatewayResolver,
            $connection,
            $clock,
        );
        $transferService = new PaymentTransferService(
            $paymentTransferRepository,
            $numberRange,
            $stateMachine,
            $gatewayResolver,
            $connection,
            $clock,
        );
        $subscriptionService = new PaymentSubscriptionService(
            $paymentRecurringRepository,
            $numberRange,
            $gatewayResolver,
            $clock,
        );
        $this->paymentService = new PaymentService($orderService, $refundService, $transferService, $subscriptionService);
        $notificationTargetResolver = new PaymentNotificationTargetResolver(
            $paymentOrderRepository,
            $paymentRefundRepository,
            $paymentTransferRepository,
            $paymentRecurringRepository,
        );
        $this->notificationService = new GatewayNotificationService(
            $paymentChannelConfigRepository,
            $paymentChannelNotifyRecordRepository,
            $paymentNotifyRecordRepository,
            new GatewayRegistry([$this->gateway]),
            $notificationTargetResolver,
            $orderService,
            $refundService,
            $transferService,
            $subscriptionService,
            $connection,
        );
    }

    public function testPaymentAndQueryReuseItsChannelConfiguration(): void
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
            'h5',
            'Order 1',
            'cny',
            returnUrl: 'https://app.example/return',
        );

        $created = $this->paymentService->pay($this->app, $request, $this->context);

        static::assertSame(1, $this->gateway->paymentCalls);
        static::assertSame($created->resourceNo, $this->gateway->lastOrder?->orderNo);
        static::assertSame('app-order-1', $created->externalResourceNo);
        static::assertNotSame($created->externalResourceNo, $created->resourceNo);

        $order = $this->loadOrder('app-order-1');
        static::assertSame($this->routeResolver->route->channelConfigId, $order->channelConfigId);
        static::assertSame('https://app.example/return', $order->returnUrl);
        static::assertSame('provider-trade-1', $order->channelTradeNo);
        static::assertEquals($this->gateway->paymentResult->toArray(), $order->primaryTransaction?->responseData);
        $primaryTransactionId = $order->primaryTransactionId;

        $this->gateway->queryResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-trade-1', resultCode: 'QUERY_SUCCESS');
        $queryResult = $this->paymentService->query($this->app, new QueryRequest(externalOrderNo: 'app-order-1'), $this->context);

        static::assertSame($created->resourceNo, $queryResult->resourceNo);
        static::assertSame(1, $this->gateway->queryCalls);
        $queriedOrder = $this->loadOrder('app-order-1');
        static::assertSame($primaryTransactionId, $queriedOrder->primaryTransactionId);
        static::assertEquals($this->gateway->paymentResult->toArray(), $queriedOrder->primaryTransaction?->responseData);
    }

    public function testPaymentRejectsAnyExistingExternalOrderReference(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-trade-duplicate');
        $this->paymentService->pay($this->app, new PaymentRequest('duplicate-order', 1000, 'h5', 'Original order', 'CNY'), $this->context);

        $this->expectExceptionObject(PaymentException::duplicateReference('duplicate-order'));

        $this->paymentService->pay($this->app, new PaymentRequest('duplicate-order', 2000, 'pc', 'Changed order', 'USD'), $this->context);
    }

    public function testPendingGatewayResultKeepsTheTransactionProcessing(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::PENDING);

        $this->paymentService->pay($this->app, new PaymentRequest('pending-order', 1000, 'h5', 'Pending order', 'CNY'), $this->context);

        $order = $this->loadOrder('pending-order');
        static::assertSame(PaymentOrderStates::STATE_PENDING, $order->state?->getTechnicalName());
        static::assertSame(PaymentTransactionStates::STATE_PROCESSING, $order->primaryTransaction?->state?->getTechnicalName());
    }

    public function testRefundReservationIsReleasedOnlyForAConfirmedFailure(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-trade-2');
        $this->paymentService->pay($this->app, new PaymentRequest('app-order-2', 1000, 'h5', 'Order 2', 'CNY'), $this->context);

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
    }

    public function testPaymentRejectsDisabledApps(): void
    {
        $disabledApp = clone $this->app;
        $disabledApp->assign(['status' => false]);

        $this->expectExceptionObject(PaymentException::appNotFound($disabledApp->appCode));

        $this->paymentService->pay($disabledApp, new PaymentRequest('disabled-app-order', 100, 'h5', 'Disabled app', 'CNY'), $this->context);
    }

    public function testPaymentRejectsGlobalWriteContext(): void
    {
        $this->expectExceptionObject(PaymentException::invalidRequest('Payment writes require a platform or tenant context.'));

        $this->paymentService->pay($this->app, new PaymentRequest('global-order', 100, 'h5', 'Global context', 'CNY'), Context::createGlobalContext());
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

    public function testPaymentNotificationUpdatesTheOrderAndIsIdempotent(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::PENDING);
        $payment = $this->paymentService->pay($this->app, new PaymentRequest(
            'notified-order',
            1500,
            'h5',
            'Notified order',
            'CNY',
            notifyUrl: 'https://app.example/payment-notify',
        ), $this->context);
        static::assertNotNull($payment->resourceNo);

        $this->gateway->notificationResult = new GatewayNotificationResult(
            PaymentNotificationTypes::PAYMENT,
            $payment->resourceNo,
            new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-notified-order'),
            'accepted',
        );
        $notification = new GatewayNotification('', parameters: ['trade_no' => 'provider-notified-order', 'status' => 'success']);

        $response = $this->notificationService->process($this->channelCode, $this->channelConfigId, $notification);
        $duplicateResponse = $this->notificationService->process(
            $this->channelCode,
            $this->channelConfigId,
            new GatewayNotification('', parameters: ['status' => 'success', 'trade_no' => 'provider-notified-order']),
        );

        static::assertSame('accepted', $response->body);
        static::assertSame('accepted', $duplicateResponse->body);
        static::assertSame(1, $this->gateway->notificationCalls);
        $order = $this->loadOrder('notified-order');
        static::assertSame(PaymentStatus::SUCCEEDED, $order->state?->getTechnicalName());
        static::assertSame('provider-notified-order', $order->channelTradeNo);

        $channelRecords = $this->repository('payment_channel_notify_record')->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $channelRecords);
        $channelRecord = $channelRecords->first();
        static::assertInstanceOf(PaymentChannelNotifyRecordEntity::class, $channelRecord);
        static::assertSame(PaymentChannelNotifyRecordStatus::STATUS_PROCESSED, $channelRecord->status);
        static::assertSame($order->getId(), $channelRecord->orderId);

        $notifyRecords = $this->repository('payment_notify_record')->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $notifyRecords);
        $notifyRecord = $notifyRecords->first();
        static::assertInstanceOf(PaymentNotifyRecordEntity::class, $notifyRecord);
        static::assertSame(PaymentNotifyRecordStatus::STATUS_PENDING, $notifyRecord->status);
        static::assertSame('https://app.example/payment-notify', $notifyRecord->notifyUrl);
        static::assertStringContainsString('"external_resource_no":"notified-order"', $notifyRecord->requestBody ?? '');
    }

    public function testNotificationRejectsAResourceCreatedWithAnotherConfiguration(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::PENDING);
        $payment = $this->paymentService->pay($this->app, new PaymentRequest('wrong-config-order', 500, 'h5', 'Wrong config', 'CNY'), $this->context);
        static::assertNotNull($payment->resourceNo);

        $otherConfigId = Uuid::randomHex();
        $this->repository('payment_channel_config')->create([[
            'id' => $otherConfigId,
            'channelId' => $this->channelId,
            'config' => ['merchantId' => 'merchant-2'],
            'status' => true,
        ]], $this->context);
        $this->gateway->notificationResult = new GatewayNotificationResult(
            PaymentNotificationTypes::PAYMENT,
            $payment->resourceNo,
            new PaymentResult(PaymentStatus::SUCCEEDED),
            'accepted',
        );

        try {
            $this->notificationService->process($this->channelCode, $otherConfigId, new GatewayNotification('wrong-config-payload'));
            static::fail('Expected the notification configuration mismatch.');
        } catch (PaymentException $exception) {
            static::assertSame(PaymentException::NOTIFICATION_CONFIGURATION_MISMATCH, $exception->getErrorCode());
        }

        $record = $this->repository('payment_channel_notify_record')->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(PaymentChannelNotifyRecordEntity::class, $record);
        static::assertSame(PaymentChannelNotifyRecordStatus::STATUS_FAILED, $record->status);
        static::assertNotNull($record->errorMessage);
    }

    public function testRefundFailureNotificationReleasesTheReservedAmount(): void
    {
        $this->gateway->paymentResult = new PaymentResult(PaymentStatus::SUCCEEDED);
        $this->paymentService->pay($this->app, new PaymentRequest('refund-notify-order', 1000, 'h5', 'Refund notify', 'CNY'), $this->context);
        $this->gateway->refundResult = new PaymentResult(PaymentStatus::PROCESSING);
        $refund = $this->paymentService->refund($this->app, new RefundRequest('refund-notify', 400, externalOrderNo: 'refund-notify-order'), $this->context);
        static::assertNotNull($refund->resourceNo);
        static::assertSame(400, $this->loadOrder('refund-notify-order')->refundedAmount);

        $this->gateway->notificationResult = new GatewayNotificationResult(
            PaymentNotificationTypes::REFUND,
            $refund->resourceNo,
            new PaymentResult(PaymentStatus::FAILED, resultCode: 'REFUND_REJECTED'),
            'accepted',
        );
        $this->notificationService->process($this->channelCode, $this->channelConfigId, new GatewayNotification('refund-failed'));

        static::assertSame(0, $this->loadOrder('refund-notify-order')->refundedAmount);
        $storedRefund = $this->findByExternalReference('payment_refund', 'externalRefundNo', 'refund-notify');
        static::assertInstanceOf(PaymentRefundEntity::class, $storedRefund);
        static::assertSame(PaymentRefundStatus::STATUS_FAILED, $storedRefund->status);
    }

    public function testTransferAndSubscriptionNotificationsUpdateTheirAggregates(): void
    {
        $this->gateway->transferResult = new PaymentResult(PaymentStatus::PROCESSING);
        $transfer = $this->paymentService->transfer($this->app, new TransferRequest(
            'notified-transfer',
            800,
            'CNY',
            'payee',
            'Payee',
            notifyUrl: 'https://app.example/transfer-notify',
        ), $this->context);
        static::assertNotNull($transfer->resourceNo);
        $this->gateway->notificationResult = new GatewayNotificationResult(
            PaymentNotificationTypes::TRANSFER,
            $transfer->resourceNo,
            new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-transfer'),
            'accepted',
        );
        $this->notificationService->process($this->channelCode, $this->channelConfigId, new GatewayNotification('transfer-success'));

        $storedTransfer = $this->findByExternalReference('payment_transfer', 'externalTransferNo', 'notified-transfer');
        static::assertInstanceOf(PaymentTransferEntity::class, $storedTransfer);
        $transferCriteria = new Criteria([$storedTransfer->getId()]);
        $transferCriteria->addAssociation('state');
        $storedTransfer = $this->repository('payment_transfer')->search($transferCriteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(PaymentTransferEntity::class, $storedTransfer);
        static::assertSame(PaymentTransferStates::STATE_SUCCEEDED, $storedTransfer->state?->getTechnicalName());

        $this->gateway->subscriptionResult = new PaymentResult(PaymentStatus::PENDING);
        $subscription = $this->paymentService->subscribe($this->app, new SubscriptionRequest(
            'notified-subscription',
            notifyUrl: 'https://app.example/subscription-notify',
        ), $this->context);
        static::assertNotNull($subscription->resourceNo);
        $this->gateway->notificationResult = new GatewayNotificationResult(
            PaymentNotificationTypes::SUBSCRIPTION,
            $subscription->resourceNo,
            new PaymentResult(PaymentStatus::SUCCEEDED, providerResourceId: 'provider-subscription'),
            'accepted',
        );
        $this->notificationService->process($this->channelCode, $this->channelConfigId, new GatewayNotification('subscription-success'));

        $storedSubscription = $this->findByExternalReference('payment_recurring', 'externalRecurringNo', 'notified-subscription');
        static::assertInstanceOf(PaymentRecurringEntity::class, $storedSubscription);
        static::assertSame(PaymentRecurringStatus::STATUS_SIGNED, $storedSubscription->status);
        static::assertSame('provider-subscription', $storedSubscription->channelRecurringNo);
        static::assertCount(2, $this->repository('payment_notify_record')->search(new Criteria(), $this->context)->getEntities());
    }

    public function testSubscriptionIdempotencyRejectsChangedSchedule(): void
    {
        $request = new SubscriptionRequest('subscription-idempotency', singleAmount: 1000, periodType: 'month', period: 1, totalPayments: 12);
        $this->paymentService->subscribe($this->app, $request, $this->context);

        $this->expectExceptionObject(PaymentException::duplicateReference($request->externalSubscriptionNo));

        $this->paymentService->subscribe($this->app, new SubscriptionRequest(
            'subscription-idempotency',
            singleAmount: 2000,
            periodType: 'month',
            period: 1,
            totalPayments: 12,
        ), $this->context);
    }

    public function testPlatformChannelConfigurationCanNotifyATenantOwnedOrder(): void
    {
        $tenantId = $this->createTenant('Payment notification tenant')->id;
        $tenantContext = Context::createTenantContext($tenantId);
        $appId = Uuid::randomHex();
        $orderId = Uuid::randomHex();
        $configId = Uuid::randomHex();
        $orderNo = 'tenant-notification-' . bin2hex(random_bytes(4));
        $this->repository('payment_channel_config')->create([[
            'id' => $configId,
            'channelId' => $this->channelId,
            'config' => ['merchantId' => 'platform-merchant'],
            'status' => true,
        ]], $this->context);
        $this->repository('payment_app')->create([[
            'id' => $appId,
            'appCode' => 'tenant-notification-' . bin2hex(random_bytes(4)),
            'appSecret' => 'tenant-secret',
            'name' => 'Tenant notification app',
            'status' => true,
        ]], $tenantContext);
        $stateId = static::getContainer()->get(StateMachineRegistry::class)
            ->getStateMachine(PaymentOrderStates::STATE_MACHINE, $tenantContext)
            ->getInitialStateId();
        static::assertNotNull($stateId);
        $this->repository('payment_order')->create([[
            'id' => $orderId,
            'paymentAppId' => $appId,
            'orderNo' => $orderNo,
            'externalOrderNo' => 'tenant-external-order',
            'amount' => 1000,
            'currencyCode' => 'CNY',
            'channelCode' => $this->channelCode,
            'methodCode' => 'h5',
            'deviceType' => 'h5',
            'subject' => 'Tenant notification order',
            'notifyUrl' => 'https://tenant.example/notify',
            'stateId' => $stateId,
            'channelConfigId' => $configId,
        ]], $tenantContext);
        $this->gateway->notificationResult = new GatewayNotificationResult(
            PaymentNotificationTypes::PAYMENT,
            $orderNo,
            new PaymentResult(PaymentStatus::SUCCEEDED),
            'accepted',
        );

        $this->notificationService->process($this->channelCode, $configId, new GatewayNotification('tenant-payment-success'));

        $record = $this->repository('payment_channel_notify_record')->search(new Criteria(), $tenantContext)->getEntities()->first();
        static::assertInstanceOf(PaymentChannelNotifyRecordEntity::class, $record);
        static::assertSame($tenantId, $record->tenantId);
        static::assertSame($orderId, $record->orderId);
        static::assertCount(0, $this->repository('payment_channel_notify_record')->search(new Criteria(), $this->context)->getEntities());
        static::assertCount(1, $this->repository('payment_notify_record')->search(new Criteria(), $tenantContext)->getEntities());
        static::assertNull($this->repository('payment_channel_config')->search(new Criteria([$configId]), $tenantContext)->getEntities()->first());
    }

    public function testPlatformOwnedReferenceDoesNotAllowCrossTenantReferences(): void
    {
        $tenantA = $this->createTenant('Payment configuration tenant A')->id;
        $tenantB = $this->createTenant('Payment configuration tenant B')->id;
        $tenantAContext = Context::createTenantContext($tenantA);
        $tenantBContext = Context::createTenantContext($tenantB);
        $tenantConfigId = Uuid::randomHex();
        $tenantAppId = Uuid::randomHex();
        $this->repository('payment_channel_config')->create([[
            'id' => $tenantConfigId,
            'channelId' => $this->channelId,
            'config' => ['merchantId' => 'tenant-a-merchant'],
            'status' => true,
        ]], $tenantAContext);
        $this->repository('payment_app')->create([[
            'id' => $tenantAppId,
            'appCode' => 'tenant-b-app-' . bin2hex(random_bytes(4)),
            'appSecret' => 'tenant-b-secret',
            'name' => 'Tenant B app',
            'status' => true,
        ]], $tenantBContext);
        $stateId = static::getContainer()->get(StateMachineRegistry::class)
            ->getStateMachine(PaymentOrderStates::STATE_MACHINE, $tenantBContext)
            ->getInitialStateId();
        static::assertNotNull($stateId);

        $this->expectException(WriteException::class);

        $this->repository('payment_order')->create([[
            'id' => Uuid::randomHex(),
            'paymentAppId' => $tenantAppId,
            'orderNo' => 'cross-tenant-' . bin2hex(random_bytes(4)),
            'externalOrderNo' => 'cross-tenant-external',
            'amount' => 100,
            'currencyCode' => 'CNY',
            'channelCode' => $this->channelCode,
            'methodCode' => 'h5',
            'deviceType' => 'h5',
            'subject' => 'Cross tenant order',
            'stateId' => $stateId,
            'channelConfigId' => $tenantConfigId,
        ]], $tenantBContext);
    }

    public function testPlatformOwnedReferenceDoesNotAllowPlatformRowsToReferenceTenantData(): void
    {
        $tenantId = $this->createTenant('Tenant payment configuration')->id;
        $tenantContext = Context::createTenantContext($tenantId);
        $tenantConfigId = Uuid::randomHex();
        $this->repository('payment_channel_config')->create([[
            'id' => $tenantConfigId,
            'channelId' => $this->channelId,
            'config' => ['merchantId' => 'tenant-merchant'],
            'status' => true,
        ]], $tenantContext);
        $stateId = static::getContainer()->get(StateMachineRegistry::class)
            ->getStateMachine(PaymentOrderStates::STATE_MACHINE, $this->context)
            ->getInitialStateId();
        static::assertNotNull($stateId);

        $this->expectException(WriteException::class);

        $this->repository('payment_order')->create([[
            'id' => Uuid::randomHex(),
            'paymentAppId' => $this->app->getId(),
            'orderNo' => 'platform-to-tenant-' . bin2hex(random_bytes(4)),
            'externalOrderNo' => 'platform-to-tenant-external',
            'amount' => 100,
            'currencyCode' => 'CNY',
            'channelCode' => $this->channelCode,
            'methodCode' => 'h5',
            'deviceType' => 'h5',
            'subject' => 'Platform to tenant order',
            'stateId' => $stateId,
            'channelConfigId' => $tenantConfigId,
        ]], $this->context);
    }

    private function loadOrder(string $externalOrderNo): PaymentOrderEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('externalOrderNo', $externalOrderNo));
        $criteria->addAssociation('state');
        $criteria->addAssociation('primaryTransaction');
        $criteria->addAssociation('primaryTransaction.state');
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
    public function __construct(public readonly PaymentRoute $route)
    {
    }

    public function getDecorated(): AbstractPaymentRouteResolver
    {
        throw new DecorationPatternException(self::class);
    }

    public function resolve(
        PaymentAppEntity $app,
        Context $context,
        PaymentRequest $request,
    ): PaymentRoute {
        return $this->route;
    }

}

/**
 * @internal
 */
final class WorkflowGateway implements PaymentHandlerInterface, QueryHandlerInterface, RefundHandlerInterface, TransferHandlerInterface, SubscribeHandlerInterface, GatewayNotificationHandlerInterface
{
    public ?PaymentOrderEntity $lastOrder = null;

    public ?PaymentRecurringEntity $lastSubscription = null;

    public ?PaymentTransferEntity $lastTransfer = null;

    public int $paymentCalls = 0;

    public PaymentResult $paymentResult;

    public int $notificationCalls = 0;

    public GatewayNotificationResult $notificationResult;

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
        $this->notificationResult = new GatewayNotificationResult(PaymentNotificationTypes::PAYMENT, 'missing', new PaymentResult(), 'accepted');
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

    public function handleNotification(GatewayNotification $notification, array $config): GatewayNotificationResult
    {
        ++$this->notificationCalls;

        return $this->notificationResult;
    }
}

<?php declare(strict_types=1);

use Contena\Core\System\Payment\AbstractPaymentService;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\Aggregate\PaymentAppTranslation\PaymentAppTranslationDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentApp\PaymentAppDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentAppChannelMethod\PaymentAppChannelMethodDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelConfig\PaymentChannelConfigDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\Aggregate\PaymentChannelTranslation\PaymentChannelTranslationDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannel\PaymentChannelDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\Aggregate\PaymentChannelMethodTranslation\PaymentChannelMethodTranslationDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelMethod\PaymentChannelMethodDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentChannelNotifyRecord\PaymentChannelNotifyRecordDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentNotifyRecord\PaymentNotifyRecordDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\Aggregate\PaymentOrderTransaction\PaymentOrderTransactionDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRefund\PaymentRefundDefinition;
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentTransfer\PaymentTransferDefinition;
use Contena\Core\System\Payment\Gateway\Alipay\AlipayGateway;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\Wechat\WechatGateway;
use Contena\Core\System\Payment\Gateway\YansongdaPayClient;
use Contena\Core\System\Payment\Gateway\YansongdaPayClientInterface;
use Contena\Core\System\Payment\Notification\GatewayNotificationService;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerRegistry;
use Contena\Core\System\Payment\OpenApi\Api\OpenApiExceptionSubscriber;
use Contena\Core\System\Payment\OpenApi\Api\PaymentController;
use Contena\Core\System\Payment\OpenApi\Api\ProviderNotificationController;
use Contena\Core\System\Payment\OpenApi\Authentication\PaymentAppValidator;
use Contena\Core\System\Payment\OpenApi\Authentication\PaymentAppValueResolver;
use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationDeliveryTask;
use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationDeliveryTaskHandler;
use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationService;
use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationSubscriber;
use Contena\Core\System\Payment\Payment\AbstractPaymentOrderService;
use Contena\Core\System\Payment\Payment\PaymentOrderConverter;
use Contena\Core\System\Payment\Payment\PaymentOrderNotificationHandler;
use Contena\Core\System\Payment\Payment\PaymentOrderPersister;
use Contena\Core\System\Payment\Payment\PaymentOrderService;
use Contena\Core\System\Payment\Payment\PaymentOrderStateHandler;
use Contena\Core\System\Payment\PaymentService;
use Contena\Core\System\Payment\Refund\AbstractPaymentRefundService;
use Contena\Core\System\Payment\Refund\PaymentRefundNotificationHandler;
use Contena\Core\System\Payment\Refund\PaymentRefundPersister;
use Contena\Core\System\Payment\Refund\PaymentRefundService;
use Contena\Core\System\Payment\Refund\PaymentRefundStateHandler;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\ConfiguredPaymentRouteProvider;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteProviderInterface;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteSelectionStrategyInterface;
use Contena\Core\System\Payment\Subscription\AbstractPaymentSubscriptionService;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionNotificationHandler;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionPersister;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionService;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionStateHandler;
use Contena\Core\System\Payment\Transfer\AbstractPaymentTransferService;
use Contena\Core\System\Payment\Transfer\PaymentTransferNotificationHandler;
use Contena\Core\System\Payment\Transfer\PaymentTransferPersister;
use Contena\Core\System\Payment\Transfer\PaymentTransferService;
use Contena\Core\System\Payment\Transfer\PaymentTransferStateHandler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    foreach ([
        PaymentAppDefinition::class,
        PaymentAppTranslationDefinition::class,
        PaymentChannelDefinition::class,
        PaymentChannelTranslationDefinition::class,
        PaymentChannelMethodDefinition::class,
        PaymentChannelMethodTranslationDefinition::class,
        PaymentAppChannelMethodDefinition::class,
        PaymentChannelConfigDefinition::class,
        PaymentOrderDefinition::class,
        PaymentOrderTransactionDefinition::class,
        PaymentRefundDefinition::class,
        PaymentTransferDefinition::class,
        PaymentRecurringDefinition::class,
        PaymentChannelNotifyRecordDefinition::class,
        PaymentNotifyRecordDefinition::class,
    ] as $definition) {
        $services->set($definition)->tag('contena.entity.definition');
    }

    $services->set(YansongdaPayClient::class);
    $services->alias(YansongdaPayClientInterface::class, YansongdaPayClient::class);
    $services->set(AlipayGateway::class)->autowire()->tag(GatewayInterface::SERVICE_TAG);
    $services->set(WechatGateway::class)->autowire()->tag(GatewayInterface::SERVICE_TAG);
    $services->set(GatewayRegistry::class)->args([tagged_iterator(GatewayInterface::SERVICE_TAG)]);
    $services->set(GatewayOperationExecutor::class)->autowire();

    $services->set(ConfiguredPaymentRouteProvider::class)->autowire()
        ->arg('$methodRepository', service('payment_app_channel_method.repository'))
        ->arg('$configRepository', service('payment_channel_config.repository'))
        ->tag(PaymentRouteProviderInterface::SERVICE_TAG);
    $services->set(PaymentRouteResolver::class)->autowire()
        ->arg('$providers', tagged_iterator(PaymentRouteProviderInterface::SERVICE_TAG))
        ->arg('$strategies', tagged_iterator(PaymentRouteSelectionStrategyInterface::SERVICE_TAG));
    $services->alias(AbstractPaymentRouteResolver::class, PaymentRouteResolver::class);
    $services->set(PaymentGatewayResolver::class)->autowire()
        ->arg('$configRepository', service('payment_channel_config.repository'));

    $services->set(PaymentOrderService::class)->autowire()
        ->arg('$paymentOrderRepository', service('payment_order.repository'))
        ->arg('$paymentOrderTransactionRepository', service('payment_order_transaction.repository'));
    $services->alias(AbstractPaymentOrderService::class, PaymentOrderService::class);
    $services->set(PaymentOrderPersister::class)->autowire()
        ->arg('$paymentOrderRepository', service('payment_order.repository'))
        ->arg('$paymentOrderTransactionRepository', service('payment_order_transaction.repository'));
    $services->set(PaymentOrderNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_order.repository'))
        ->arg('$transactionRepository', service('payment_order_transaction.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);
    $services->set(PaymentOrderConverter::class)->autowire();
    $services->set(PaymentOrderStateHandler::class)->autowire()
        ->arg('$paymentOrderRepository', service('payment_order.repository'))
        ->arg('$paymentOrderTransactionRepository', service('payment_order_transaction.repository'));

    $services->set(PaymentRefundService::class)->autowire()
        ->arg('$paymentOrderRepository', service('payment_order.repository'))
        ->arg('$paymentRefundRepository', service('payment_refund.repository'));
    $services->alias(AbstractPaymentRefundService::class, PaymentRefundService::class);
    $services->set(PaymentRefundPersister::class)->autowire()
        ->arg('$paymentRefundRepository', service('payment_refund.repository'));
    $services->set(PaymentRefundStateHandler::class)->autowire()
        ->arg('$paymentRefundRepository', service('payment_refund.repository'));
    $services->set(PaymentRefundNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_refund.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);

    $services->set(PaymentTransferService::class)->autowire()
        ->arg('$paymentTransferRepository', service('payment_transfer.repository'));
    $services->alias(AbstractPaymentTransferService::class, PaymentTransferService::class);
    $services->set(PaymentTransferPersister::class)->autowire()
        ->arg('$paymentTransferRepository', service('payment_transfer.repository'));
    $services->set(PaymentTransferStateHandler::class)->autowire()
        ->arg('$paymentTransferRepository', service('payment_transfer.repository'));
    $services->set(PaymentTransferNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_transfer.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);

    $services->set(PaymentSubscriptionService::class)->autowire()
        ->arg('$paymentRecurringRepository', service('payment_recurring.repository'));
    $services->alias(AbstractPaymentSubscriptionService::class, PaymentSubscriptionService::class);
    $services->set(PaymentSubscriptionPersister::class)->autowire()
        ->arg('$paymentRecurringRepository', service('payment_recurring.repository'));
    $services->set(PaymentSubscriptionStateHandler::class)->autowire()
        ->arg('$paymentRecurringRepository', service('payment_recurring.repository'));
    $services->set(PaymentSubscriptionNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_recurring.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);

    $services->set(PaymentNotificationHandlerRegistry::class)->args([tagged_iterator(PaymentNotificationHandlerInterface::SERVICE_TAG)]);
    $services->set(GatewayNotificationService::class)->autowire()
        ->arg('$channelConfigRepository', service('payment_channel_config.repository'))
        ->arg('$channelNotifyRecordRepository', service('payment_channel_notify_record.repository'));

    $services->set(PaymentService::class)->autowire();
    $services->alias(AbstractPaymentService::class, PaymentService::class);

    $services->set(PaymentController::class)->autowire()->public();
    $services->set(ProviderNotificationController::class)->autowire()->public();
    $services->set(OpenApiExceptionSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(PaymentAppValidator::class)->autowire()
        ->arg('$paymentAppRepository', service('payment_app.repository'))
        ->tag('kernel.event_subscriber');
    $services->set(PaymentAppValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1001]);
    $services->set(AppNotificationSubscriber::class)->autowire()
        ->arg('$repository', service('payment_notify_record.repository'))
        ->tag('kernel.event_subscriber');
    $services->set('payment.notification.http_client', NoPrivateNetworkHttpClient::class)
        ->args([service('http_client')]);
    $services->set(AppNotificationService::class)->autowire()
        ->arg('$notifyRecordRepository', service('payment_notify_record.repository'))
        ->arg('$httpClient', service('payment.notification.http_client'));
    $services->set(AppNotificationDeliveryTask::class)->tag('contena.scheduled.task');
    $services->set(AppNotificationDeliveryTaskHandler::class)->autowire()
        ->arg('$scheduledTaskRepository', service('scheduled_task.repository'));
};

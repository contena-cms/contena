<?php declare(strict_types=1);

use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Payment\Configuration\ChannelConfigReader;
use Contena\Core\System\Payment\Configuration\ChannelConfigValidator;
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
use Contena\Core\System\Payment\Gateway\GatewayExecutor;
use Contena\Core\System\Payment\Gateway\GatewayExecutorInterface;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\Wechat\WechatGateway;
use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Service\AbstractPaymentService;
use Contena\Core\System\Payment\Service\PaymentOrderService;
use Contena\Core\System\Payment\Service\PaymentRefundService;
use Contena\Core\System\Payment\Service\PaymentService;
use Contena\Core\System\Payment\Service\PaymentSubscriptionService;
use Contena\Core\System\Payment\Service\PaymentTransferService;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ChannelConfigReader::class)->autowire();
    $services->set(ChannelConfigValidator::class);

    $services->set(GatewayExecutor::class);
    $services->alias(GatewayExecutorInterface::class, GatewayExecutor::class);

    $services->set(AlipayGateway::class)
        ->autowire()
        ->tag(GatewayInterface::SERVICE_TAG);
    $services->set(WechatGateway::class)
        ->autowire()
        ->tag(GatewayInterface::SERVICE_TAG);
    $services->set(GatewayRegistry::class)
        ->args([tagged_iterator(GatewayInterface::SERVICE_TAG)]);

    $services->set(PaymentRouteResolver::class)
        ->args([
            service('payment_app_channel_method.repository'),
            service('payment_channel_config.repository'),
            service(GatewayRegistry::class),
            service(ChannelConfigValidator::class),
            service('event_dispatcher'),
        ]);
    $services->alias(AbstractPaymentRouteResolver::class, PaymentRouteResolver::class);

    $services->set(PaymentOrderService::class)
        ->args([
            service('payment_order.repository'),
            service('payment_order_transaction.repository'),
            service(AbstractNumberRangeValueGenerator::class),
            service(StateMachineRegistry::class),
            service(AbstractPaymentRouteResolver::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(ClockInterface::class),
        ]);
    $services->set(PaymentRefundService::class)
        ->args([
            service('payment_refund.repository'),
            service(PaymentOrderService::class),
            service(AbstractNumberRangeValueGenerator::class),
            service(AbstractPaymentRouteResolver::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(ClockInterface::class),
        ]);
    $services->set(PaymentTransferService::class)
        ->args([
            service('payment_transfer.repository'),
            service(AbstractNumberRangeValueGenerator::class),
            service(StateMachineRegistry::class),
            service(AbstractPaymentRouteResolver::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(ClockInterface::class),
        ]);
    $services->set(PaymentSubscriptionService::class)
        ->args([
            service('payment_recurring.repository'),
            service(AbstractNumberRangeValueGenerator::class),
            service(AbstractPaymentRouteResolver::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);
    $services->set(PaymentService::class)
        ->args([
            service(PaymentOrderService::class),
            service(PaymentRefundService::class),
            service(PaymentTransferService::class),
            service(PaymentSubscriptionService::class),
        ]);
    $services->alias(AbstractPaymentService::class, PaymentService::class);

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
};

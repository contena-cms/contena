<?php declare(strict_types=1);

use Contena\Core\System\Payment\Channel\Configuration\ChannelConfigReader;
use Contena\Core\System\Payment\Channel\Configuration\ChannelConfigValidator;
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
use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOperation\PaymentOperationDefinition;
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
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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
        PaymentOperationDefinition::class,
    ] as $definition) {
        $services->set($definition)->tag('contena.entity.definition');
    }
};

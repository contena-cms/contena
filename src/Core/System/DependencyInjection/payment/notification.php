<?php declare(strict_types=1);

use Contena\Core\System\Payment\Notification\GatewayNotificationService;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentNotificationHandlerRegistry::class)->args([tagged_iterator(PaymentNotificationHandlerInterface::SERVICE_TAG)]);
    $services->set(GatewayNotificationService::class)->autowire()
        ->arg('$channelConfigRepository', service('payment_channel_config.repository'))
        ->arg('$channelNotifyRecordRepository', service('payment_channel_notify_record.repository'));
};

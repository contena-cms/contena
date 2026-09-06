<?php declare(strict_types=1);

use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Refund\AbstractPaymentRefundService;
use Contena\Core\System\Payment\Refund\PaymentRefundNotificationHandler;
use Contena\Core\System\Payment\Refund\PaymentRefundPersister;
use Contena\Core\System\Payment\Refund\PaymentRefundService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentRefundService::class)->autowire();
    $services->alias(AbstractPaymentRefundService::class, PaymentRefundService::class);
    $services->set(PaymentRefundPersister::class)->autowire()
        ->arg('$paymentRefundRepository', service('payment_refund.repository'));
    $services->set(PaymentRefundNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_refund.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);
};

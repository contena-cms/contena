<?php declare(strict_types=1);

use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Transfer\AbstractPaymentTransferService;
use Contena\Core\System\Payment\Transfer\PaymentTransferNotificationHandler;
use Contena\Core\System\Payment\Transfer\PaymentTransferPersister;
use Contena\Core\System\Payment\Transfer\PaymentTransferService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentTransferService::class)->autowire();
    $services->alias(AbstractPaymentTransferService::class, PaymentTransferService::class);
    $services->set(PaymentTransferPersister::class)->autowire()
        ->arg('$paymentTransferRepository', service('payment_transfer.repository'));
    $services->set(PaymentTransferNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_transfer.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);
};

<?php declare(strict_types=1);

use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Payment\AbstractPaymentOrderService;
use Contena\Core\System\Payment\Payment\PaymentOrderConverter;
use Contena\Core\System\Payment\Payment\PaymentOrderLoader;
use Contena\Core\System\Payment\Payment\PaymentOrderNotificationHandler;
use Contena\Core\System\Payment\Payment\PaymentOrderPersister;
use Contena\Core\System\Payment\Payment\PaymentOrderService;
use Contena\Core\System\Payment\Payment\PaymentOrderStateHandler;
use Contena\Core\System\Payment\Payment\RefundAmountReservation;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentOrderService::class)->autowire();
    $services->alias(AbstractPaymentOrderService::class, PaymentOrderService::class);
    $services->set(PaymentOrderPersister::class)->autowire()
        ->arg('$paymentOrderRepository', service('payment_order.repository'))
        ->arg('$paymentOrderTransactionRepository', service('payment_order_transaction.repository'));
    $services->set(PaymentOrderNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_order.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);
    $services->set(PaymentOrderConverter::class)->autowire();
    $services->set(PaymentOrderStateHandler::class)->autowire();
    $services->set(RefundAmountReservation::class)->autowire();
    $services->set(PaymentOrderLoader::class)->autowire()->arg('$repository', service('payment_order.repository'));
};

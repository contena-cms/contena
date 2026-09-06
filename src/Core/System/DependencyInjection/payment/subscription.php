<?php declare(strict_types=1);

use Contena\Core\System\Payment\Notification\PaymentNotificationHandlerInterface;
use Contena\Core\System\Payment\Subscription\AbstractPaymentSubscriptionService;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionNotificationHandler;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionPersister;
use Contena\Core\System\Payment\Subscription\PaymentSubscriptionService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentSubscriptionService::class)->autowire();
    $services->alias(AbstractPaymentSubscriptionService::class, PaymentSubscriptionService::class);
    $services->set(PaymentSubscriptionPersister::class)->autowire()
        ->arg('$paymentRecurringRepository', service('payment_recurring.repository'));
    $services->set(PaymentSubscriptionNotificationHandler::class)->autowire()
        ->arg('$repository', service('payment_recurring.repository'))
        ->tag(PaymentNotificationHandlerInterface::SERVICE_TAG);
};

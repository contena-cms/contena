<?php declare(strict_types=1);

use Contena\Core\System\Payment\Gateway\GatewayOperationExecutor;
use Contena\Core\System\Payment\PaymentAppGuard;
use Contena\Core\System\Payment\Service\AbstractPaymentService;
use Contena\Core\System\Payment\Service\PaymentService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(PaymentAppGuard::class);
    $services->set(GatewayOperationExecutor::class)->autowire();
    $services->set(PaymentService::class)->autowire();
    $services->alias(AbstractPaymentService::class, PaymentService::class);
};

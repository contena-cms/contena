<?php declare(strict_types=1);

use Contena\Core\System\Payment\Routing\AbstractPaymentRouteResolver;
use Contena\Core\System\Payment\Routing\ConfiguredPaymentRouteProvider;
use Contena\Core\System\Payment\Routing\FirstAvailableRouteStrategy;
use Contena\Core\System\Payment\Routing\PaymentGatewayResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteProviderInterface;
use Contena\Core\System\Payment\Routing\PaymentRouteResolver;
use Contena\Core\System\Payment\Routing\PaymentRouteSelectionStrategyInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ConfiguredPaymentRouteProvider::class)->autowire()
        ->arg('$methodRepository', service('payment_app_channel_method.repository'))
        ->arg('$configRepository', service('payment_channel_config.repository'))
        ->tag(PaymentRouteProviderInterface::SERVICE_TAG);
    $services->set(FirstAvailableRouteStrategy::class)
        ->tag(PaymentRouteSelectionStrategyInterface::SERVICE_TAG, ['priority' => -1000]);
    $services->set(PaymentRouteResolver::class)->autowire()
        ->arg('$providers', tagged_iterator(PaymentRouteProviderInterface::SERVICE_TAG))
        ->arg('$strategies', tagged_iterator(PaymentRouteSelectionStrategyInterface::SERVICE_TAG));
    $services->alias(AbstractPaymentRouteResolver::class, PaymentRouteResolver::class);
    $services->set(PaymentGatewayResolver::class)->autowire()
        ->arg('$configRepository', service('payment_channel_config.repository'));
};

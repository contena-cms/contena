<?php declare(strict_types=1);

use Contena\Core\System\Payment\Gateway\Alipay\AlipayGateway;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\Wechat\WechatGateway;
use Contena\Core\System\Payment\Gateway\YansongdaPayClient;
use Contena\Core\System\Payment\Gateway\YansongdaPayClientInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(YansongdaPayClient::class);
    $services->alias(YansongdaPayClientInterface::class, YansongdaPayClient::class);
    $services->set(AlipayGateway::class)->autowire()->tag(GatewayInterface::SERVICE_TAG);
    $services->set(WechatGateway::class)->autowire()->tag(GatewayInterface::SERVICE_TAG);
    $services->set(GatewayRegistry::class)->args([tagged_iterator(GatewayInterface::SERVICE_TAG)]);
};

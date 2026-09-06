<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentOrder\PaymentOrderEntity;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversClass(GatewayRegistry::class)]
final class GatewayRegistryTest extends TestCase
{
    public function testPluginGatewayNeedsOnlyItsCapabilityAndServiceTag(): void
    {
        $container = new ContainerBuilder();
        $configuration = \dirname(__DIR__, 6) . '/src/Core/System/DependencyInjection/payment';
        new PhpFileLoader($container, new FileLocator($configuration))->load('gateway.php');
        $container->register(PluginPaymentGateway::class)->addTag(GatewayInterface::SERVICE_TAG);
        $container->setAlias('test.payment.gateway_registry', GatewayRegistry::class)->setPublic(true);
        $container->compile();

        $registry = $container->get('test.payment.gateway_registry');
        static::assertInstanceOf(GatewayRegistry::class, $registry);
        static::assertInstanceOf(PluginPaymentGateway::class, $registry->get('plugin_gateway'));
        static::assertTrue($registry->has('alipay'));
        static::assertTrue($registry->has('wechat'));
    }

    public function testReturnsRegisteredGateway(): void
    {
        $gateway = new class implements GatewayInterface {
            public function code(): string
            {
                return 'provider';
            }
        };
        $registry = new GatewayRegistry([$gateway]);

        static::assertSame($gateway, $registry->get('provider'));
    }

    public function testMissingGatewayHasItsOwnException(): void
    {
        $registry = new GatewayRegistry([]);

        $this->expectExceptionObject(PaymentException::gatewayNotFound('missing'));

        $registry->get('missing');
    }
}

/**
 * @internal
 */
final class PluginPaymentGateway implements PaymentHandlerInterface
{
    public function code(): string
    {
        return 'plugin_gateway';
    }

    public function pay(PaymentOrderEntity $order, array $config): PaymentResult
    {
        return new PaymentResult();
    }
}

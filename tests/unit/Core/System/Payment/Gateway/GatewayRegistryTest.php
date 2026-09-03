<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\DataAbstractionLayer\PaymentRecurring\PaymentRecurringEntity;
use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\Gateway\PaymentOperation;
use Contena\Core\System\Payment\Gateway\SubscribeHandlerInterface;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\System\Payment\Struct\PaymentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GatewayRegistry::class)]
final class GatewayRegistryTest extends TestCase
{
    public function testGatewayOnlySupportsCapabilitiesItImplements(): void
    {
        $gateway = new class implements SubscribeHandlerInterface {
            public function code(): string
            {
                return 'agreement-only';
            }

            public function subscribe(PaymentRecurringEntity $subscription, array $config): PaymentResult
            {
                return new PaymentResult();
            }
        };
        $registry = new GatewayRegistry([$gateway]);

        static::assertTrue($registry->supports('agreement-only', PaymentOperation::SUBSCRIBE));
        static::assertFalse($registry->supports('agreement-only', PaymentOperation::PAY));
        static::assertFalse($registry->supports('agreement-only', 'authorize'));
        static::assertFalse($registry->supports('unknown-provider', PaymentOperation::SUBSCRIBE));
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

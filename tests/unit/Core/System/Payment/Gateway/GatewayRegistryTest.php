<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Gateway\GatewayInterface;
use Contena\Core\System\Payment\Gateway\GatewayRegistry;
use Contena\Core\System\Payment\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GatewayRegistry::class)]
final class GatewayRegistryTest extends TestCase
{
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

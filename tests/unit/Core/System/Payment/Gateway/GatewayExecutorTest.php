<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Gateway\GatewayExecutor;
use Contena\Core\System\Payment\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GatewayExecutor::class)]
final class GatewayExecutorTest extends TestCase
{
    public function testInitializesYansongdaWithPhpDiContainer(): void
    {
        $executor = new GatewayExecutor();

        $this->expectExceptionObject(PaymentException::capabilityNotSupported('alipay', 'not-supported'));

        $executor->execute('alipay', ['app_id' => 'test'], 'not-supported', []);
    }
}

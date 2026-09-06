<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\Gateway;

use Contena\Core\System\Payment\Gateway\YansongdaPayClient;
use Contena\Core\System\Payment\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(YansongdaPayClient::class)]
final class YansongdaPayClientTest extends TestCase
{
    public function testNormalizesTheSdkHttpResponseWithoutAProviderRequest(): void
    {
        $response = new YansongdaPayClient()->request('alipay', ['app_id' => 'test'], 'success', []);

        static::assertSame(['_http_status' => 200, '_headers' => [], '_body' => 'success'], $response);
    }

    public function testInitializesYansongdaWithPhpDiContainer(): void
    {
        $executor = new YansongdaPayClient();

        $this->expectExceptionObject(PaymentException::capabilityNotSupported('alipay', 'not-supported'));

        $executor->request('alipay', ['app_id' => 'test'], 'not-supported', []);
    }
}

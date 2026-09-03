<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\OpenApi\Authentication;

use Contena\Core\System\Payment\OpenApi\Authentication\RequestSignature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RequestSignature::class)]
final class RequestSignatureTest extends TestCase
{
    public function testSignsParametersInStableTopLevelOrder(): void
    {
        $parameters = [
            'timestamp' => '1700000000',
            'channel_extra' => ['scene' => 'store'],
            'empty' => '',
            'amount' => 1200,
            'sign' => 'ignored',
        ];

        static::assertSame(
            hash_hmac('sha256', 'amount=1200&channel_extra={"scene":"store"}&timestamp=1700000000&key=secret', 'secret'),
            RequestSignature::sign($parameters, 'secret'),
        );
    }

    public function testVerifiesOnlySignaturesInsideTheTimestampWindow(): void
    {
        $parameters = ['app_id' => 'app', 'timestamp' => '1700000000', 'amount' => 1200];
        $parameters['sign'] = RequestSignature::sign($parameters, 'secret');

        static::assertTrue(RequestSignature::verify($parameters, 'secret', 1700000300));
        static::assertFalse(RequestSignature::verify($parameters, 'secret', 1700000301));

        $parameters['sign'] = str_repeat('0', 64);
        static::assertFalse(RequestSignature::verify($parameters, 'secret', 1700000000));
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Currency\CurrencyCollection;
use Contena\Core\System\Payment\PaymentCurrencyValidator;
use Contena\Core\System\Payment\PaymentException;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PaymentCurrencyValidator::class)]
final class PaymentCurrencyValidatorTest extends TestCase
{
    public function testConfiguredCurrencyIsNormalized(): void
    {
        $repository = StaticEntityRepository::of(CurrencyCollection::class, [[Uuid::randomHex()]]);
        $validator = new PaymentCurrencyValidator($repository);

        static::assertSame('CNY', $validator->validate('cny', Context::createDefaultContext()));
    }

    public function testUnknownCurrencyIsRejected(): void
    {
        $repository = StaticEntityRepository::of(CurrencyCollection::class, [[]]);
        $validator = new PaymentCurrencyValidator($repository);

        $this->expectExceptionObject(PaymentException::currencyNotSupported('ZZZ'));

        $validator->validate('zzz', Context::createDefaultContext());
    }
}

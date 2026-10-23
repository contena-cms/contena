<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Currency;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CurrencyException::class)]
class CurrencyExceptionTest extends TestCase
{
    public function testIsoCodeNotUnique(): void
    {
        $exception = CurrencyException::isoCodeNotUnique('EUR');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CurrencyException::ISO_CODE_NOT_UNIQUE, $exception->getErrorCode());
        static::assertSame('The ISO code "EUR" is already in use.', $exception->getMessage());
    }
}

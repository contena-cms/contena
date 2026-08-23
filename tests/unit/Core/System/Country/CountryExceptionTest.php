<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Country;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Country\CountryException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CountryException::class)]
class CountryExceptionTest extends TestCase
{
    public function testCountryNotFound(): void
    {
        $exception = CountryException::countryNotFound('id-1');

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CountryException::COUNTRY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find country with id "id-1"', $exception->getMessage());
        static::assertSame(['entity' => 'country', 'field' => 'id', 'value' => 'id-1'], $exception->getParameters());
    }
}

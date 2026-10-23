<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Store\Exception\LicenseNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(LicenseNotFoundException::class)]
class LicenseNotFoundExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__LICENSE_NOT_FOUND',
            (new LicenseNotFoundException(1234))->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_NOT_FOUND,
            (new LicenseNotFoundException(1234))->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Could not find license with id 1234',
            (new LicenseNotFoundException(1234))->getMessage()
        );
    }
}

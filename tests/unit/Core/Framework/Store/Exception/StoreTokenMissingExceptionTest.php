<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Exception\StoreTokenMissingException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(StoreTokenMissingException::class)]
class StoreTokenMissingExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_TOKEN_IS_MISSING',
            (new StoreTokenMissingException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            (new StoreTokenMissingException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Store token is missing',
            (new StoreTokenMissingException())->getMessage()
        );
    }
}

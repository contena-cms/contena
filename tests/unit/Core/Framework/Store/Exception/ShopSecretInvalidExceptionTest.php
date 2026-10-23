<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Exception\ShopSecretInvalidException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ShopSecretInvalidException::class)]
class ShopSecretInvalidExceptionTest extends TestCase
{
    public function testGetErrorCode(): void
    {
        static::assertSame(
            'FRAMEWORK__STORE_SHOP_SECRET_INVALID',
            (new ShopSecretInvalidException())->getErrorCode()
        );
    }

    public function testGetStatusCode(): void
    {
        static::assertSame(
            Response::HTTP_FORBIDDEN,
            (new ShopSecretInvalidException())->getStatusCode()
        );
    }

    public function testGetMessage(): void
    {
        static::assertSame(
            'Store shop secret is invalid',
            (new ShopSecretInvalidException())->getMessage()
        );
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Controller\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\Exception\MissingAppSecretException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(MissingAppSecretException::class)]
class MissingAppSecretExceptionTest extends TestCase
{
    public function testMissingAppSecretException(): void
    {
        $exception = new MissingAppSecretException();

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame('ADMINISTRATION__MISSING_APP_SECRET', $exception->getErrorCode());
        static::assertSame('Failed to retrieve app secret.', $exception->getMessage());
        static::assertEmpty($exception->getParameters());
    }
}

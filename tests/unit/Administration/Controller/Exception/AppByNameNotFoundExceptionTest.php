<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\Controller\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Administration\Controller\Exception\AppByNameNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(AppByNameNotFoundException::class)]
class AppByNameNotFoundExceptionTest extends TestCase
{
    public function testAppByNameNotFoundException(): void
    {
        $exception = new AppByNameNotFoundException('appName');

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame('ADMINISTRATION__APP_BY_NAME_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('The provided name appName is invalid and no app could be found.', $exception->getMessage());
        static::assertSame(['name' => 'appName'], $exception->getParameters());
    }
}

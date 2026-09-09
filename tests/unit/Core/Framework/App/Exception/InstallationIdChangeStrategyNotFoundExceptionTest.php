<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Exception;

use Contena\Core\Framework\App\Exception\InstallationIdChangeStrategyNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(InstallationIdChangeStrategyNotFoundException::class)]
class InstallationIdChangeStrategyNotFoundExceptionTest extends TestCase
{
    public function testException(): void
    {
        $e = new InstallationIdChangeStrategyNotFoundException('testStrategy');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_INSTALLATION_ID_CHANGE_STRATEGY_NOT_FOUND', $e->getErrorCode());
        static::assertSame('Installation ID change resolver with name "testStrategy" not found.', $e->getMessage());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Acl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Api\Acl\ApiCallerType;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ApiCallerType::class)]
class ApiCallerTypeTest extends TestCase
{
    public function testAUserIsIdentifiedByItsUserId(): void
    {
        $source = new AdminApiSource('admin_user-id');

        static::assertSame(ApiCallerType::AdminUser, ApiCallerType::of($source));
        static::assertSame('admin_user-id', ApiCallerType::AdminUser->idOf($source));
    }

    public function testAnIntegrationIsIdentifiedByItsIntegrationId(): void
    {
        $source = new AdminApiSource(null, 'integration-id');

        static::assertSame(ApiCallerType::Integration, ApiCallerType::of($source));
        static::assertSame('integration-id', ApiCallerType::Integration->idOf($source));
    }

    public function testAnIntegrationNamingAUserIsStillTheIntegration(): void
    {
        $source = new AdminApiSource(Uuid::randomHex(), 'integration-id');

        static::assertSame(ApiCallerType::Integration, ApiCallerType::of($source));
        static::assertSame('integration-id', ApiCallerType::Integration->idOf($source));
    }

    public function testASourceOutsideTheAdminApiIsNoCaller(): void
    {
        static::assertNull(ApiCallerType::of(new SystemSource()));
        static::assertNull(ApiCallerType::AdminUser->idOf(new SystemSource()));
    }
}

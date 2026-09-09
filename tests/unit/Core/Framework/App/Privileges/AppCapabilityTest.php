<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Privileges;

use Contena\Core\Framework\App\Privileges\AppCapability;
use Contena\Core\Framework\App\Privileges\Privileges;
use Contena\Core\Framework\Uuid\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppCapability::class)]
class AppCapabilityTest extends TestCase
{
    public function testCanReturnsTrueWhenActionGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['member:read', 'blog:read']]);

        static::assertTrue(new AppCapability($privileges)->can($appId, 'blog:read'));
    }

    public function testCanReturnsFalseWhenActionNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read']]);

        static::assertFalse(new AppCapability($privileges)->can($appId, 'blog:read'));
    }

    public function testCanReturnsFalseWhenAppUnknown(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([]);

        static::assertFalse(new AppCapability($privileges)->can($appId, 'blog:read'));
    }

    public function testWhenGrantedRunsCallbackAndReturnsItsResult(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['blog:read']]);

        $result = new AppCapability($privileges)->whenGranted($appId, 'blog:read', static fn (): string => 'dispatched');

        static::assertSame('dispatched', $result);
    }

    public function testWhenGrantedSkipsCallbackAndReturnsNullWhenNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['customer:read']]);

        $called = false;
        $result = new AppCapability($privileges)->whenGranted($appId, 'blog:read', static function () use (&$called): string {
            $called = true;

            return 'dispatched';
        });

        static::assertNull($result);
        static::assertFalse($called, 'callback must not run when the permission is not granted');
    }
}

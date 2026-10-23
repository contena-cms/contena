<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Privileges;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Contena\Core\Framework\App\Privileges\AppCapability;
use Contena\Core\Framework\App\Privileges\Privileges;
use Contena\Core\Framework\Uuid\Uuid;

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
        $privileges->method('getPrivileges')->willReturn([$appId => ['user:read', Tax::PERMISSION]]);

        static::assertTrue((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }

    public function testCanReturnsFalseWhenActionNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['user:read']]);

        static::assertFalse((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }

    public function testCanReturnsFalseWhenAppUnknown(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([]);

        static::assertFalse((new AppCapability($privileges))->can($appId, Tax::PERMISSION));
    }

    public function testWhenGrantedRunsCallbackAndReturnsItsResult(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => [Tax::PERMISSION]]);

        $result = (new AppCapability($privileges))->whenGranted($appId, Tax::PERMISSION, static fn (): string => 'dispatched');

        static::assertSame('dispatched', $result);
    }

    public function testWhenGrantedSkipsCallbackAndReturnsNullWhenNotGranted(): void
    {
        $appId = Uuid::randomHex();

        $privileges = static::createStub(Privileges::class);
        $privileges->method('getPrivileges')->willReturn([$appId => ['user:read']]);

        $called = false;
        $result = (new AppCapability($privileges))->whenGranted($appId, Tax::PERMISSION, static function () use (&$called): string {
            $called = true;

            return 'dispatched';
        });

        static::assertNull($result);
        static::assertFalse($called, 'callback must not run when the permission is not granted');
    }
}

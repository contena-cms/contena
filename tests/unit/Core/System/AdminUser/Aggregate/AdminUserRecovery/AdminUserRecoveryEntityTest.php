<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\AdminUser\Aggregate\AdminUserRecovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryEntity;
use Contena\Core\System\AdminUser\AdminUserEntity;

/**
 * @internal
 */
#[CoversClass(AdminUserRecoveryEntity::class)]
class AdminUserRecoveryEntityTest extends TestCase
{
    protected function tearDown(): void
    {
        FieldVisibility::$isInTwigRenderingContext = false;
    }

    public function testAccessorsRoundTrip(): void
    {
        $admin_user = new AdminUserEntity();

        $recovery = new AdminUserRecoveryEntity();
        $recovery->setUserId('admin_user-id');
        $recovery->setUser($admin_user);

        static::assertSame('admin_user-id', $recovery->getUserId());
        static::assertSame($admin_user, $recovery->getUser());
    }

    public function testHashIsReadableOutsideTwig(): void
    {
        $recovery = $this->recoveryWithInternalHash();
        $recovery->setHash('hash-value');

        static::assertSame('hash-value', $recovery->getHash());
    }

    public function testHashIsGuardedInsideTwig(): void
    {
        $recovery = $this->recoveryWithInternalHash();
        $recovery->setHash('hash-value');

        FieldVisibility::$isInTwigRenderingContext = true;

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('hash', AdminUserRecoveryEntity::class));
        $recovery->getHash();
    }

    private function recoveryWithInternalHash(): AdminUserRecoveryEntity
    {
        $recovery = new AdminUserRecoveryEntity();
        $recovery->internalSetEntityData(AdminUserRecoveryDefinition::ENTITY_NAME, new FieldVisibility(['hash']));

        return $recovery;
    }
}

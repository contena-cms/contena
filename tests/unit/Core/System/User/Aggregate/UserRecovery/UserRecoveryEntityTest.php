<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Aggregate\UserRecovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(UserRecoveryEntity::class)]
class UserRecoveryEntityTest extends TestCase
{
    public function testAccessorsRoundTrip(): void
    {
        $user = new UserEntity();

        $recovery = new UserRecoveryEntity();
        $recovery->setUserId('user-id');
        $recovery->setUser($user);

        static::assertSame('user-id', $recovery->getUserId());
        static::assertSame($user, $recovery->getUser());
    }

    public function testHashIsReadableOutsideTwig(): void
    {
        $recovery = $this->createRecoveryWithInternalHash();
        $recovery->setHash('hash-value');

        static::assertSame('hash-value', $recovery->getHash());
    }

    public function testHashIsNotReadableInTwig(): void
    {
        $recovery = $this->createRecoveryWithInternalHash();
        $recovery->setHash('hash-value');

        $this->expectExceptionObject(DataAbstractionLayerException::internalFieldAccessNotAllowed('hash', UserRecoveryEntity::class));

        FieldVisibility::$isInTwigRenderingContext = true;

        try {
            $recovery->getHash();
        } finally {
            FieldVisibility::$isInTwigRenderingContext = false;
        }
    }

    private function createRecoveryWithInternalHash(): UserRecoveryEntity
    {
        $recovery = new UserRecoveryEntity();
        $recovery->internalSetEntityData(UserRecoveryDefinition::ENTITY_NAME, new FieldVisibility(['hash']));

        return $recovery;
    }
}

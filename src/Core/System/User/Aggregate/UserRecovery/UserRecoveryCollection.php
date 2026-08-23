<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserRecovery;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserRecoveryEntity>
 */
class UserRecoveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_recovery_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserRecoveryEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserGroup;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserGroupEntity>
 *
 * @codeCoverageIgnore
 */
class UserGroupCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserGroupEntity::class;
    }
}

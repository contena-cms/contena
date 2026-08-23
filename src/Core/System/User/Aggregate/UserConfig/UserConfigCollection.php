<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserConfig;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserConfigEntity>
 */
class UserConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserConfigEntity::class;
    }
}

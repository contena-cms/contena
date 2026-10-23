<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<UserGroupEntity, UserGroupCollection>
 */
class UserGroupProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return UserGroupDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}

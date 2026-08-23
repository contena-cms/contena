<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<UserRecoveryEntity, UserRecoveryCollection>
 */
class UserRecoveryProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return UserRecoveryDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('user');

        return $criteria;
    }
}

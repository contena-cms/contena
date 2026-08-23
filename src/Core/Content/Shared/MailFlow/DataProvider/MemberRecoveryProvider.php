<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryCollection;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryDefinition;
use Contena\Core\System\Member\Aggregate\MemberRecovery\MemberRecoveryEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<MemberRecoveryEntity, MemberRecoveryCollection>
 */
class MemberRecoveryProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return MemberRecoveryDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('member');

        return $criteria;
    }
}

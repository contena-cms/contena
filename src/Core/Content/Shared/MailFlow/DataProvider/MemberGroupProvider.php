<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupCollection;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<MemberGroupEntity, MemberGroupCollection>
 */
class MemberGroupProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return MemberGroupDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}

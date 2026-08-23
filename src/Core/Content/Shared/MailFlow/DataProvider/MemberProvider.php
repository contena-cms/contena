<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<MemberEntity, MemberCollection>
 */
class MemberProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return MemberDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'addresses.country',
            'addresses.region',
        ]);

        return $criteria;
    }
}

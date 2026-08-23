<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberGroup;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MemberGroupEntity>
 */
class MemberGroupCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'member_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return MemberGroupEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Aggregate\MemberRecovery;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<MemberRecoveryEntity>
 */
class MemberRecoveryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'member_recovery_collection';
    }

    protected function getExpectedClass(): string
    {
        return MemberRecoveryEntity::class;
    }
}

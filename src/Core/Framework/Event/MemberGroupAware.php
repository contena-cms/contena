<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

#[IsFlowEventAware]
interface MemberGroupAware
{
    public const string MEMBER_GROUP_ID = 'memberGroupId';

    public const string MEMBER_GROUP = 'memberGroup';

    public function getMemberGroupId(): string;
}

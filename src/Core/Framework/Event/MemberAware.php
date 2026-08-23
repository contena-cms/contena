<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

#[IsFlowEventAware]
interface MemberAware
{
    public const string MEMBER_ID = 'memberId';

    public const string MEMBER = 'member';

    public function getMemberId(): string;
}

<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Overview;

use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Page\Page;

class AccountOverviewPage extends Page
{
    protected MemberEntity $member;

    public function getMember(): MemberEntity
    {
        return $this->member;
    }

    public function setMember(MemberEntity $member): void
    {
        $this->member = $member;
    }
}

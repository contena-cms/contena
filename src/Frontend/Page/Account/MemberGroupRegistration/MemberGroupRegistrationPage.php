<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\MemberGroupRegistration;

use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Frontend\Page\Account\Login\AccountLoginPage;

class MemberGroupRegistrationPage extends AccountLoginPage
{
    protected MemberGroupEntity $memberGroup;

    public function setGroup(MemberGroupEntity $memberGroup): void
    {
        $this->memberGroup = $memberGroup;
    }

    public function getGroup(): MemberGroupEntity
    {
        return $this->memberGroup;
    }
}

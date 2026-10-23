<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\UserGroupRegistration;

use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Frontend\Page\Account\Login\AccountLoginPage;

class UserGroupRegistrationPage extends AccountLoginPage
{
    protected UserGroupEntity $userGroup;

    public function setGroup(UserGroupEntity $userGroup): void
    {
        $this->userGroup = $userGroup;
    }

    public function getGroup(): UserGroupEntity
    {
        return $this->userGroup;
    }
}

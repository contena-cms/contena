<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\CustomerGroupRegistration;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Account\Login\AccountLoginPage;

#[Package('checkout')]
class CustomerGroupRegistrationPage extends AccountLoginPage
{
    protected CustomerGroupEntity $customerGroup;

    public function setGroup(CustomerGroupEntity $customerGroup): void
    {
        $this->customerGroup = $customerGroup;
    }

    public function getGroup(): CustomerGroupEntity
    {
        return $this->customerGroup;
    }
}

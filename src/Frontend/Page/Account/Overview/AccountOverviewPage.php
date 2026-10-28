<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Overview;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Page;

#[Package('checkout')]
class AccountOverviewPage extends Page
{

    protected CustomerEntity $customer;




    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }


}

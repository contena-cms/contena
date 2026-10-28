<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Login;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Storefront\Page\Page;

#[Package('checkout')]
class AccountLoginPage extends Page
{
    protected CountryCollection $countries;


    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }


}

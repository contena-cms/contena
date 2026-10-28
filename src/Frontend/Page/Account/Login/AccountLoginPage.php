<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Login;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Storefront\Page\Page;

#[Package('checkout')]
class AccountLoginPage extends Page
{
    protected CountryCollection $countries;

    protected SalutationCollection $salutations;

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }
}

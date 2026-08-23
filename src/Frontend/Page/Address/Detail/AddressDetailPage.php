<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Detail;

use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Frontend\Page\Page;

class AddressDetailPage extends Page
{
    protected ?MemberAddressEntity $address = null;

    protected CountryCollection $countries;

    public function getAddress(): ?MemberAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?MemberAddressEntity $address): void
    {
        $this->address = $address;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }
}

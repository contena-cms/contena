<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Listing;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryCollection;
use Contena\Frontend\Page\Page;

class AddressListingPage extends Page
{
    protected UserAddressCollection $addresses;

    protected CountryCollection $countries;

    protected ?UserAddressEntity $address = null;

    public function getAddresses(): UserAddressCollection
    {
        return $this->addresses;
    }

    public function setAddresses(UserAddressCollection $addresses): void
    {
        $this->addresses = $addresses;
    }

    public function getSalutations(): SalutationCollection
    {
        return $this->salutations;
    }

    public function setSalutations(SalutationCollection $salutations): void
    {
        $this->salutations = $salutations;
    }

    public function getCountries(): CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function setCart(Cart $cart): void
    {
        $this->cart = $cart;
    }

    public function getAddress(): ?UserAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?UserAddressEntity $address): void
    {
        $this->address = $address;
    }
}

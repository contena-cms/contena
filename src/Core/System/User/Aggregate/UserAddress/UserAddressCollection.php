<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserAddress;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateCollection;
use Contena\Core\System\Country\CountryCollection;

/**
 * @extends EntityCollection<UserAddressEntity>
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Frontend\Controller\AddressControllerTest
 */
class UserAddressCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getUserIds(): array
    {
        return $this->fmap(static fn (UserAddressEntity $userAddress) => $userAddress->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(static fn (UserAddressEntity $userAddress) => $userAddress->getUserId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCountryIds(): array
    {
        return $this->fmap(static fn (UserAddressEntity $userAddress) => $userAddress->getCountryId());
    }

    public function filterByCountryId(string $id): self
    {
        return $this->filter(static fn (UserAddressEntity $userAddress) => $userAddress->getCountryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getCountryStateIds(): array
    {
        return $this->fmap(static fn (UserAddressEntity $userAddress) => $userAddress->getCountryStateId());
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(static fn (UserAddressEntity $userAddress) => $userAddress->getCountryStateId() === $id);
    }

    public function getCountries(): CountryCollection
    {
        return new CountryCollection(
            $this->fmap(static fn (UserAddressEntity $userAddress) => $userAddress->getCountry())
        );
    }

    public function getCountryStates(): CountryStateCollection
    {
        return new CountryStateCollection(
            $this->fmap(static fn (UserAddressEntity $userAddress) => $userAddress->getCountryState())
        );
    }

    public function sortByDefaultAddress(UserEntity $user): UserAddressCollection
    {
        $this->sort(static function (UserAddressEntity $a, UserAddressEntity $b) use ($user) {
            if ($a->getId() === $user->getDefaultBillingAddressId() || $a->getId() === $user->getDefaultShippingAddressId()) {
                return -1;
            }

            if ($b->getId() === $user->getDefaultBillingAddressId() || $b->getId() === $user->getDefaultShippingAddressId()) {
                return 1;
            }

            return 0;
        });

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'user_address_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserAddressEntity::class;
    }
}

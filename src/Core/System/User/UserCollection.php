<?php declare(strict_types=1);

namespace Contena\Core\System\User;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelCollection;

/**
 * @extends EntityCollection<UserEntity>
 *
 * @codeCoverageIgnore
 */
class UserCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getGroupIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getGroupId());
    }

    public function filterByGroupId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => $user->getGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getChannelIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getChannelId());
    }

    public function filterByChannelId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => $user->getChannelId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getLanguageId());
    }

    /**
     * @return array<string>
     */
    public function getLastPaymentMethodIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getLastPaymentMethodId());
    }

    public function filterByLastPaymentMethodId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => $user->getLastPaymentMethodId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getDefaultBillingAddressIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getDefaultBillingAddressId());
    }

    public function filterByDefaultBillingAddressId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => $user->getDefaultBillingAddressId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getDefaultShippingAddressIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getDefaultShippingAddressId());
    }

    public function filterByDefaultShippingAddressId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => $user->getDefaultShippingAddressId() === $id);
    }

    public function getGroups(): UserGroupCollection
    {
        return new UserGroupCollection(
            $this->fmap(static fn (UserEntity $user) => $user->getGroup())
        );
    }

    public function getChannels(): ChannelCollection
    {
        return new ChannelCollection(
            $this->fmap(static fn (UserEntity $user) => $user->getChannel())
        );
    }

    public function getLastPaymentMethods(): PaymentMethodCollection
    {
        return new PaymentMethodCollection(
            $this->fmap(static fn (UserEntity $user) => $user->getLastPaymentMethod())
        );
    }

    public function getDefaultBillingAddress(): UserAddressCollection
    {
        return new UserAddressCollection(
            $this->fmap(static fn (UserEntity $user) => $user->getDefaultBillingAddress())
        );
    }

    public function getDefaultShippingAddress(): UserAddressCollection
    {
        return new UserAddressCollection(
            $this->fmap(static fn (UserEntity $user) => $user->getDefaultShippingAddress())
        );
    }

    /**
     * @return array<array<string>>
     */
    public function getListVatIds(): array
    {
        return $this->fmap(static fn (UserEntity $user) => $user->getVatIds());
    }

    public function filterByVatId(string $id): self
    {
        return $this->filter(static fn (UserEntity $user) => \in_array($id, $user->getVatIds() ?? [], true));
    }

    public function getApiAlias(): string
    {
        return 'user_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserEntity::class;
    }
}

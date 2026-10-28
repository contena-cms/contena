<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Aggregate\CustomerWishlist;

use Contena\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductCollection;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CustomerWishlistEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $customerId;

    protected string $salesChannelId;

    protected ?CustomerEntity $customer = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?CustomerWishlistProductCollection $products = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getProducts(): ?CustomerWishlistProductCollection
    {
        return $this->products;
    }

    public function setProducts(CustomerWishlistProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}

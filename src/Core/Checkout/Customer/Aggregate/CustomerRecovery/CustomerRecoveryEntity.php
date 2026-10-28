<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Contena\Core\Framework\Log\Package;

#[Package('checkout')]
class CustomerRecoveryEntity extends Entity
{
    use EntityIdTrait;

    protected string $customerId;

    protected string $hash;

    protected ?CustomerEntity $customer = null;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getHash(): string
    {
        $this->checkIfPropertyAccessIsAllowed('hash');

        return $this->hash;
    }

    public function setHash(string $hash): void
    {
        $this->hash = $hash;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}

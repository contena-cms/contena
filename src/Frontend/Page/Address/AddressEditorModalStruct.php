<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Address;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Storefront\Page\Page;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AddressEditorModalStruct extends Struct
{
    protected bool $changeBilling = false;

    protected bool $changeShipping = false;

    protected bool $success = false;

    protected ?string $addressId = null;

    /**
     * @var array<string, mixed>
     */
    protected array $messages = [];

    protected ?CustomerAddressEntity $address = null;


    public function isChangeBilling(): bool
    {
        return $this->changeBilling;
    }

    public function setChangeBilling(bool $changeBilling): void
    {
        $this->changeBilling = $changeBilling;
    }

    public function isChangeShipping(): bool
    {
        return $this->changeShipping;
    }

    public function setChangeShipping(bool $changeShipping): void
    {
        $this->changeShipping = $changeShipping;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function setSuccess(bool $success): void
    {
        $this->success = $success;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): void
    {
        $this->addressId = $addressId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @param array<string, mixed> $messages
     */
    public function setMessages(array $messages): void
    {
        $this->messages = $messages;
    }

    public function getAddress(): ?CustomerAddressEntity
    {
        return $this->address;
    }

    public function setAddress(?CustomerAddressEntity $address): void
    {
        $this->address = $address;
    }


}

<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Event\CustomerGroupAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\SalesChannelAware;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('checkout')]
class CustomerGroupRegistrationAccepted extends Event implements SalesChannelAware, CustomerAware, MailAware, CustomerGroupAware, FlowEventAware
{
    final public const EVENT_NAME = 'customer.group.registration.accepted';

    /**
     * @internal
     */
    public function __construct(
        private readonly CustomerEntity $customer,
        private readonly CustomerGroupEntity $customerGroup,
        private readonly Context $context,
        private readonly ?MailRecipientStruct $mailRecipientStruct = null
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(CustomerGroupAware::CUSTOMER_GROUP, new EntityType(CustomerGroupDefinition::class));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct) {
            return $this->mailRecipientStruct;
        }

        return new MailRecipientStruct(
            [
                $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
            ]
        );
    }

    public function getSalesChannelId(): string
    {
        return $this->customer->getSalesChannelId();
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getCustomerGroup(): CustomerGroupEntity
    {
        return $this->customerGroup;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getCustomerGroupId(): string
    {
        return $this->customerGroup->getId();
    }
}

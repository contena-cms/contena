<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

use Monolog\Level;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Contena\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\EventData\ScalarValueType;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\SalesChannelAware;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\LogAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerLoginEvent extends Event implements SalesChannelAware, ContenaSalesChannelEvent, CustomerAware, MailAware, ScalarValuesAware, FlowEventAware, LogAware
{
    final public const EVENT_NAME = 'checkout.customer.login';

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
        private readonly string $contextToken
    ) {
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [
            FlowMailVariables::CONTEXT_TOKEN => $this->contextToken,
        ];
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getContextToken(): string
    {
        return $this->contextToken;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(FlowMailVariables::CONTEXT_TOKEN, new ScalarValueType(ScalarValueType::TYPE_STRING), [EventDataCollection::HIDDEN_FROM_WEBHOOK => true]);
    }

    public function getLogData(): array
    {
        return [
            'customerId' => $this->customer->getId(),
            'customerNumber' => $this->customer->getCustomerNumber(),
        ];
    }

    public function getLogLevel(): Level
    {
        return Level::Info;
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct(
            [
                $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
            ]
        );
    }
}

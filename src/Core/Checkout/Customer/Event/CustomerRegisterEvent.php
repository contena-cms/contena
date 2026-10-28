<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\CustomerAware;
use Contena\Core\Framework\Event\EventData\EntityType;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\EventData\MailRecipientStruct;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\MailAware;
use Contena\Core\Framework\Event\SalesChannelAware;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Content\Flow\SendMailActionTest
 */
#[Package('checkout')]
class CustomerRegisterEvent extends Event implements SalesChannelAware, ContenaSalesChannelEvent, CustomerAware, MailAware, FlowEventAware
{
    public const EVENT_NAME = 'checkout.customer.register';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer
    ) {
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

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class));
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            $this->mailRecipientStruct = new MailRecipientStruct([
                $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

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
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerPasswordChangedEvent extends Event implements SalesChannelAware, ContenaSalesChannelEvent, CustomerAware, MailAware, ScalarValuesAware, FlowEventAware
{
    public const EVENT_NAME = 'customer.password.changed';

    private readonly string $shopName;

    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
        private readonly CustomerEntity $customer,
    ) {
        $this->shopName = $salesChannelContext->getSalesChannel()->getTranslation('name');
    }

    public function getCustomerId(): string
    {
        return $this->customer->getId();
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add(CustomerAware::CUSTOMER, new EntityType(CustomerDefinition::class))
            ->add(FlowMailVariables::SHOP_NAME, new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return new MailRecipientStruct([
            $this->customer->getEmail() => $this->customer->getFirstName() . ' ' . $this->customer->getLastName(),
        ]);
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelContext->getSalesChannelId();
    }

    public function getValues(): array
    {
        return [
            FlowMailVariables::SHOP_NAME => $this->shopName,
        ];
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getShopName(): string
    {
        return $this->shopName;
    }
}

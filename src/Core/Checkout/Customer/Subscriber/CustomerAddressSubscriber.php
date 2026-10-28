<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Subscriber;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Contena\Core\Checkout\Customer\CustomerEvents;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerAddressSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.' . CustomerEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.customer_address.partial_loaded' => 'salesChannelLoaded',
        ];
    }

    /**
     * @param SalesChannelEntityLoadedEvent<CustomerAddressEntity|PartialEntity> $event
     */
    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {
        if (!$event->getSalesChannelContext()->getCustomer()) {
            return;
        }

        $defaultBillingAddressId = $event->getSalesChannelContext()->getCustomer()->getDefaultBillingAddressId();
        $defaultShippingAddressId = $event->getSalesChannelContext()->getCustomer()->getDefaultShippingAddressId();

        foreach ($event->getEntities() as $customerAddress) {
            $customerAddress->assign([
                'isDefaultBillingAddress' => $customerAddress->getId() === $defaultBillingAddressId,
                'isDefaultShippingAddress' => $customerAddress->getId() === $defaultShippingAddressId,
            ]);
        }
    }
}

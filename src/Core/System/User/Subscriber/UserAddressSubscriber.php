<?php declare(strict_types=1);

namespace Contena\Core\System\User\Subscriber;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEvents;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class UserAddressSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'channel.' . UserEvents::CUSTOMER_ADDRESS_LOADED_EVENT => 'channelLoaded',
            'channel.user_address.partial_loaded' => 'channelLoaded',
        ];
    }

    /**
     * @param ChannelEntityLoadedEvent<UserAddressEntity|PartialEntity> $event
     */
    public function channelLoaded(ChannelEntityLoadedEvent $event): void
    {
        if (!$event->getChannelContext()->getUser()) {
            return;
        }

        $defaultBillingAddressId = $event->getChannelContext()->getUser()->getDefaultBillingAddressId();
        $defaultShippingAddressId = $event->getChannelContext()->getUser()->getDefaultShippingAddressId();

        foreach ($event->getEntities() as $userAddress) {
            $userAddress->assign([
                'isDefaultBillingAddress' => $userAddress->getId() === $defaultBillingAddressId,
                'isDefaultShippingAddress' => $userAddress->getId() === $defaultShippingAddressId,
            ]);
        }
    }
}

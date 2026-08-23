<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Core\System\Member\MemberEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AddressHashSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            MemberEvents::MEMBER_ADDRESS_LOADED_EVENT => 'generateAddressHash',
        ];
    }

    /**
     * @param EntityLoadedEvent<MemberAddressEntity> $event
     */
    public function generateAddressHash(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $address) {
            $address->setHash(Hasher::hash([
                'firstName' => $address->getFirstName(),
                'lastName' => $address->getLastName(),
                'zipcode' => $address->getZipcode(),
                'city' => $address->getCity(),
                'title' => $address->getTitle(),
                'street' => $address->getStreet(),
                'additionalAddressLine1' => $address->getAdditionalAddressLine1(),
                'additionalAddressLine2' => $address->getAdditionalAddressLine2(),
                'countryId' => $address->getCountryId(),
                'regionId' => $address->getRegionId(),
            ], 'sha256'));
        }
    }
}

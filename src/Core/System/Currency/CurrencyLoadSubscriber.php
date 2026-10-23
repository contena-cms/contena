<?php declare(strict_types=1);

namespace Contena\Core\System\Currency;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CurrencyLoadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CurrencyEvents::CURRENCY_LOADED_EVENT => 'setDefault',
            'currency.partial_loaded' => 'setDefault',
        ];
    }

    /**
     * @param EntityLoadedEvent<CurrencyEntity|PartialEntity> $event
     */
    public function setDefault(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            $entity->assign([
                'isSystemDefault' => ($entity->get('id') === Defaults::CURRENCY),
            ]);
        }
    }
}

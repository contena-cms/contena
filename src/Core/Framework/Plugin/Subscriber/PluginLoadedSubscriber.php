<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class PluginLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::PLUGIN_LOADED_EVENT => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<PluginEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $plugin) {
            if ($plugin->getIconRaw()) {
                $plugin->setIcon(base64_encode($plugin->getIconRaw()));
            }
        }
    }
}

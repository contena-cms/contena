<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Subscriber;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal only for use by the app-system
 */
class AppLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'app.loaded' => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<AppEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $app) {
            $iconRaw = $app->getIconRaw();
            if ($iconRaw !== null) {
                $app->setIcon(base64_encode($iconRaw));
            }
        }
    }
}

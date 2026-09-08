<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Flow\Action;

use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionEntity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AppFlowActionLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'app_flow_action.loaded' => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<AppFlowActionEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $appFlowAction) {
            $iconRaw = $appFlowAction->getIconRaw();

            if ($iconRaw !== null) {
                $appFlowAction->setIcon(base64_encode($iconRaw));
            }
        }
    }
}

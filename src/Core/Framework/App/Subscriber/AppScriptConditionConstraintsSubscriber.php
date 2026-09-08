<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Subscriber;

use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AppScriptConditionConstraintsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'app_script_condition.loaded' => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<AppScriptConditionEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        foreach ($event->getEntities() as $entity) {
            $constraints = $entity->getConstraints();

            if (!\is_string($constraints)) {
                continue;
            }

            /** @phpstan-ignore contena.unserializeUsage */
            $entity->setConstraints(\unserialize($constraints));
        }
    }
}

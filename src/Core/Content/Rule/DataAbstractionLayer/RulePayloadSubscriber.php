<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule\DataAbstractionLayer;

use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Content\Rule\RuleEvents;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\Container;
use Contena\Core\Framework\Rule\Container\FilterRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\ScriptRule;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class RulePayloadSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly RulePayloadUpdater $updater,
        private readonly ContainerInterface $container,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            RuleEvents::RULE_LOADED_EVENT => 'unserialize',
        ];
    }

    /**
     * @param EntityLoadedEvent<RuleEntity> $event
     */
    public function unserialize(EntityLoadedEvent $event): void
    {
        $this->indexIfNeeded($event);

        foreach ($event->getEntities() as $entity) {
            $payload = $entity->getPayload();
            if ($payload === null || !\is_string($payload)) {
                continue;
            }

            /** @phpstan-ignore contena.unserializeUsage */
            $payload = \unserialize($payload);

            $this->enrichConditions([$payload]);

            $entity->setPayload($payload);
        }
    }

    /**
     * @param EntityLoadedEvent<RuleEntity> $event
     */
    private function indexIfNeeded(EntityLoadedEvent $event): void
    {
        $rules = [];

        foreach ($event->getEntities() as $rule) {
            if ($rule->getPayload() === null && !$rule->isInvalid()) {
                $rules[$rule->getId()] = $rule;
            }
        }

        if ($rules === []) {
            return;
        }

        $updated = $this->updater->update(array_keys($rules));

        foreach ($updated as $id => $entity) {
            $rules[$id]->assign($entity);
        }
    }

    /**
     * @param list<Rule> $conditions
     */
    private function enrichConditions(array $conditions): void
    {
        foreach ($conditions as $condition) {
            if ($condition instanceof ScriptRule) {
                $condition->configureDependencies($this->container);

                continue;
            }

            if ($condition instanceof Container || $condition instanceof FilterRule) {
                $this->enrichConditions($condition->getRules());
            }
        }
    }
}

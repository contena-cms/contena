<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Events;

use Contena\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Event\GenericEvent;
use Contena\Core\Framework\Feature;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.8.0 - Will be removed - use MailFlowDataCriteriaEvent instead
 */
class BeforeLoadStorableFlowDataEvent extends Event implements ContenaEvent, GenericEvent
{
    public function __construct(
        private readonly string $entityName,
        private readonly Criteria $criteria,
        private readonly Context $context,
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0', MailFlowDataCriteriaEvent::class)
        );
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return 'flow.storer.' . $this->entityName . '.criteria.event';
    }

    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return $this->criteria;
    }

    public function getEntityName(): string
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return $this->entityName;
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'));

        return $this->context;
    }
}

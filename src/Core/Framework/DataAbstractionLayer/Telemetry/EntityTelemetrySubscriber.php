<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Telemetry;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class EntityTelemetrySubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Meter $meter)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntitySearchedEvent::class => ['emitAssociationsCountMetric', 99],
        ];
    }

    public function emitAssociationsCountMetric(EntitySearchedEvent $event): void
    {
        $criteria = $event->getCriteria();
        $associationsCount = $this->getAssociationsCountFromCriteria($criteria);
        $this->meter->emit(new ConfiguredMetric(
            name: 'dal.associations.count',
            value: $associationsCount,
        ));
    }

    private function getAssociationsCountFromCriteria(Criteria $criteria): int
    {
        return array_reduce(
            $criteria->getAssociations(),
            fn (int $carry, Criteria $association) => $carry + 1 + $this->getAssociationsCountFromCriteria($association),
            0
        );
    }
}

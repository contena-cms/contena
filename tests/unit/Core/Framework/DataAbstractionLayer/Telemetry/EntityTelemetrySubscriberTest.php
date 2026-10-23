<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityTelemetrySubscriber;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Metrics\Metric\ConfiguredMetric;

/**
 * @internal
 */
#[CoversClass(EntityTelemetrySubscriber::class)]
class EntityTelemetrySubscriberTest extends TestCase
{
    public function testEmitAssociationsCountMetric(): void
    {
        $criteria = new Criteria();
        $criteria->addAssociation('association1');
        $criteria->addAssociation('association2');

        $event = new EntitySearchedEvent($criteria, static::createStub(EntityDefinition::class), Context::createDefaultContext());
        $meter = $this->createMock(Meter::class);
        $meter->expects($this->once())
            ->method('emit')
            ->with(static::callback(static function (ConfiguredMetric $metric) {
                return $metric->name === 'dal.associations.count' && $metric->value === 2;
            }));

        $subscriber = new EntityTelemetrySubscriber($meter);
        $subscriber->emitAssociationsCountMetric($event);
    }
}

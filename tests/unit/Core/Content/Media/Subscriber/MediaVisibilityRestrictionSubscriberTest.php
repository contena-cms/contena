<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\BeforeEntityAggregationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\System\Country\CountryDefinition;

/**
 * @internal
 */
#[CoversClass(MediaVisibilityRestrictionSubscriber::class)]
class MediaVisibilityRestrictionSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            EntitySearchedEvent::class => 'securePrivateFolders',
            BeforeEntityAggregationEvent::class => 'securePrivateMediaAggregation',
        ], MediaVisibilityRestrictionSubscriber::getSubscribedEvents());
    }

    public function testSystemContextIsNotRestricted(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createCLIContext()
        );

        new MediaVisibilityRestrictionSubscriber()->securePrivateFolders($event);

        static::assertSame([], $event->getCriteria()->getFilters());
    }

    public function testUserScopeRestrictsPrivateMedia(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        new MediaVisibilityRestrictionSubscriber()->securePrivateFolders($event);

        $filter = $event->getCriteria()->getFilters()[0] ?? null;
        static::assertInstanceOf(EqualsFilter::class, $filter);
        static::assertSame('private', $filter->getField());
        static::assertFalse($filter->getValue());
    }

    public function testUserScopeRestrictsPrivateMediaFolders(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new MediaFolderDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        new MediaVisibilityRestrictionSubscriber()->securePrivateFolders($event);

        $filter = $event->getCriteria()->getFilters()[0] ?? null;
        static::assertInstanceOf(MultiFilter::class, $filter);
        static::assertSame(MultiFilter::CONNECTION_OR, $filter->getOperator());
        static::assertCount(2, $filter->getQueries());
    }

    public function testMediaAggregationIsRestricted(): void
    {
        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('media-count', 'id'));
        $event = new BeforeEntityAggregationEvent(
            $criteria,
            new MediaDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        new MediaVisibilityRestrictionSubscriber()->securePrivateMediaAggregation($event);

        $aggregation = $criteria->getAggregation('Sanitized media-count');
        static::assertInstanceOf(FilterAggregation::class, $aggregation);
        static::assertInstanceOf(CountAggregation::class, $aggregation->getAggregation());
    }

    public function testOtherEntitiesAreNotRestricted(): void
    {
        $event = new EntitySearchedEvent(
            new Criteria(),
            new CountryDefinition(),
            Context::createDefaultContext(new AdminApiSource(null))
        );

        new MediaVisibilityRestrictionSubscriber()->securePrivateFolders($event);

        static::assertSame([], $event->getCriteria()->getFilters());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\Processor\BehaviorListingProcessor;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BehaviorListingProcessor::class)]
class BehaviorProcessorTest extends TestCase
{
    public function testPrepareWithNoAggregations(): void
    {
        $request = new Request(['no-aggregations' => true]);
        $criteria = new Criteria();
        $context = static::createStub(ChannelContext::class);

        (new BehaviorListingProcessor())->prepare($request, $criteria, $context);

        static::assertCount(0, $criteria->getAggregations());
    }

    public function testPrepareWithOnlyAggregations(): void
    {
        $request = new Request(['only-aggregations' => true]);
        $criteria = new Criteria();
        $context = static::createStub(ChannelContext::class);

        (new BehaviorListingProcessor())->prepare($request, $criteria, $context);

        static::assertSame(0, $criteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
        static::assertCount(0, $criteria->getSorting());
        static::assertCount(0, $criteria->getAssociations());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
class CompositeProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testComposition(): void
    {
        $request = new Request();
        $criteria = new Criteria();
        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $request->query->set('no-aggregations', true);
        static::getContainer()->get(CompositeListingProcessor::class)->prepare($request, $criteria, $context);
        static::assertCount(0, $criteria->getAggregations());

        $request->query->set('only-aggregations', true);
        static::getContainer()->get(CompositeListingProcessor::class)->prepare($request, $criteria, $context);
        static::assertCount(0, $criteria->getSorting());
        static::assertCount(0, $criteria->getAssociations());
        static::assertSame(0, $criteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
    }
}

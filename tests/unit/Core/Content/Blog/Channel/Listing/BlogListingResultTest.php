<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[CoversClass(BlogListingResult::class)]
class BlogListingResultTest extends TestCase
{
    public function testFromSearchResultCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $listing = BlogListingResult::fromSearchResult($source);

        static::assertSame($source->getTotal(), $listing->getTotal());
        static::assertSame($source->getEntities(), $listing->getEntities());
        static::assertSame($source->getCriteria(), $listing->getCriteria());
        static::assertSame($source->getContext(), $listing->getContext());
    }

    public function testFromSearchResultSetsListingSpecificFields(): void
    {
        $sortings = new BlogSortingCollection();

        $listing = BlogListingResult::fromSearchResult(
            $this->createSearchResult(),
            availableSortings: $sortings,
            sorting: 'title-asc',
            currentFilters: ['category' => 'news'],
        );

        static::assertSame($sortings, $listing->getAvailableSortings());
        static::assertSame('title-asc', $listing->getSorting());
        static::assertSame(['category' => 'news'], $listing->getCurrentFilters());
    }

    public function testFromSearchResultUsesDefaultsWhenExtrasOmitted(): void
    {
        $listing = BlogListingResult::fromSearchResult($this->createSearchResult());

        static::assertNull($listing->getSorting());
        static::assertSame([], $listing->getCurrentFilters());
        static::assertCount(0, $listing->getAvailableSortings());
    }

    public function testFromSearchResultKeepsPaginationAggregationsExtensionsAndStates(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(20);

        $source = new EntitySearchResult(
            42,
            new BlogCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );
        $source->addExtension('custom', new ArrayStruct(['foo' => 'bar']));
        $source->addState('custom-state');

        $listing = BlogListingResult::fromSearchResult($source);

        static::assertSame(10, $listing->getLimit());
        static::assertSame(3, $listing->getPage());
        static::assertSame($source->getAggregations(), $listing->getAggregations());
        static::assertSame($source->getExtension('custom'), $listing->getExtension('custom'));
        static::assertTrue($listing->hasState('custom-state'));
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        $entities = new BlogCollection();

        return new EntitySearchResult(
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewEntity;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Content\Blog\Channel\Review\RatingMatrix;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 */
#[CoversClass(BlogReviewResult::class)]
class BlogReviewResultTest extends TestCase
{
    public function testFromSearchResultCopiesResultProperties(): void
    {
        $source = $this->createSearchResult();

        $result = BlogReviewResult::fromSearchResult(
            $source,
            matrix: new RatingMatrix([]),
            blogId: 'blog-1',
            totalReviewsInCurrentLanguage: 5,
        );

        static::assertSame($source->getTotal(), $result->getTotal());
        static::assertSame($source->getEntities(), $result->getEntities());
        static::assertSame($source->getCriteria(), $result->getCriteria());
        static::assertSame($source->getContext(), $result->getContext());
    }

    public function testFromSearchResultSetsReviewSpecificFields(): void
    {
        $matrix = new RatingMatrix([]);
        $userReview = new BlogReviewEntity();

        $result = BlogReviewResult::fromSearchResult(
            $this->createSearchResult(),
            matrix: $matrix,
            blogId: 'blog-1',
            totalReviewsInCurrentLanguage: 7,
            userReview: $userReview,
            parentId: 'parent-1',
        );

        static::assertSame($matrix, $result->getMatrix());
        static::assertSame('blog-1', $result->getBlogId());
        static::assertSame(7, $result->getTotalReviewsInCurrentLanguage());
        static::assertSame($userReview, $result->getUserReview());
        static::assertSame('parent-1', $result->getParentId());
    }

    public function testFromSearchResultUsesDefaultsForOptionalExtras(): void
    {
        $result = BlogReviewResult::fromSearchResult(
            $this->createSearchResult(),
            matrix: new RatingMatrix([]),
            blogId: 'blog-1',
            totalReviewsInCurrentLanguage: 0,
        );

        static::assertNull($result->getUserReview());
        static::assertNull($result->getParentId());
    }

    public function testFromSearchResultKeepsPaginationAggregationsExtensionsAndStates(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setOffset(20);

        $source = new EntitySearchResult(
            BlogReviewDefinition::ENTITY_NAME,
            42,
            new BlogReviewCollection(),
            new AggregationResultCollection(),
            $criteria,
            Context::createDefaultContext(),
        );
        $source->addExtension('custom', new ArrayStruct(['foo' => 'bar']));
        $source->addState('custom-state');

        $result = BlogReviewResult::fromSearchResult(
            $source,
            matrix: new RatingMatrix([]),
            blogId: 'blog-1',
            totalReviewsInCurrentLanguage: 0,
        );

        static::assertSame(10, $result->getLimit());
        static::assertSame(3, $result->getPage());
        static::assertSame($source->getAggregations(), $result->getAggregations());
        static::assertSame($source->getExtension('custom'), $result->getExtension('custom'));
        static::assertTrue($result->hasState('custom-state'));
    }

    /**
     * @return EntitySearchResult<BlogReviewCollection>
     */
    private function createSearchResult(): EntitySearchResult
    {
        $entities = new BlogReviewCollection();

        return new EntitySearchResult(
            BlogReviewDefinition::ENTITY_NAME,
            $entities->count(),
            $entities,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}

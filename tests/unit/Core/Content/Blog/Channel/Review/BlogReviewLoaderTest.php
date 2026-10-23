<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewEntity;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogReviewLoader::class)]
class BlogReviewLoaderTest extends TestCase
{
    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = new StaticSystemConfigService();
        $this->systemConfigService->set('core.listing.reviewsPerPage', 10, 'channelId');
    }

    public function testItLoadsReviewsWithBlogId(): void
    {
        $reviewId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $request = new Request();
        $channelContext = $this->getChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new BlogReviewCollection([
            $review,
        ]);

        $blogReviewRoute = static::createStub(BlogReviewRoute::class);
        $blogReviewLoader = $this->getBlogReviewLoader($blogReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $channelContext);

        $blogReviewRoute
            ->method('load')
            ->willReturn(
                new BlogReviewRouteResponse($reviewResult)
            );

        $result = $blogReviewLoader->load($request, $channelContext, $blogId);

        static::assertInstanceOf(BlogReviewEntity::class, $result->getEntities()->first());
        static::assertSame($result->getEntities()->first()->getId(), $reviewId);
        static::assertCount(1, $result->getEntities());
        static::assertNull($result->getUserReview());
    }

    public function testItLoadsReviewsPagination(): void
    {
        $reviewId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $request = new Request(['p' => 2]);
        $channelContext = $this->getChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new BlogReviewCollection([
            $review,
        ]);

        $blogReviewRoute = $this->createMock(BlogReviewRoute::class);
        $blogReviewLoader = $this->getBlogReviewLoader($blogReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $channelContext);

        $criteria = $this->createCriteria($request, $channelContext);

        $blogReviewRoute->expects($this->once())->method('load')->willReturnCallback(
            static function (string $actualBlogId, Request $actualRequest, ChannelContext $actualContext, Criteria $actualCriteria) use ($blogId, $request, $channelContext, $criteria, $reviewResult): BlogReviewRouteResponse {
                static::assertSame($blogId, $actualBlogId);
                static::assertSame($request, $actualRequest);
                static::assertSame($channelContext, $actualContext);
                static::assertEquals($criteria, $actualCriteria);

                return new BlogReviewRouteResponse($reviewResult);
            }
        );

        $result = $blogReviewLoader->load($request, $channelContext, $blogId);

        $firstResult = $result->getEntities()->first();
        static::assertInstanceOf(BlogReviewEntity::class, $firstResult);
        static::assertSame($firstResult->getId(), $reviewId);
        static::assertSame($result->getCriteria()->getOffset(), 10);
        static::assertCount(1, $result->getEntities());
        static::assertNull($result->getUserReview());
    }

    public function testNegativeOffsetDefaultsToZero(): void
    {
        $reviewId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $request = new Request(['p' => -2]);
        $channelContext = $this->getChannelContext(false);

        $review = $this->getReviewEntity($reviewId);

        $reviews = new BlogReviewCollection([
            $review,
        ]);

        $blogReviewRoute = $this->createMock(BlogReviewRoute::class);
        $blogReviewLoader = $this->getBlogReviewLoader($blogReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $channelContext);

        $criteria = $this->createCriteria($request, $channelContext);

        $blogReviewRoute->expects($this->once())->method('load')->willReturnCallback(
            static function (string $actualBlogId, Request $actualRequest, ChannelContext $actualContext, Criteria $actualCriteria) use ($blogId, $request, $channelContext, $criteria, $reviewResult): BlogReviewRouteResponse {
                static::assertSame($blogId, $actualBlogId);
                static::assertSame($request, $actualRequest);
                static::assertSame($channelContext, $actualContext);
                static::assertEquals($criteria, $actualCriteria);

                return new BlogReviewRouteResponse($reviewResult);
            }
        );

        $result = $blogReviewLoader->load($request, $channelContext, $blogId);

        static::assertInstanceOf(BlogReviewEntity::class, $result->getEntities()->first());
        static::assertSame($result->getEntities()->first()->getId(), $reviewId);
        static::assertSame($result->getCriteria()->getOffset(), 0);
        static::assertCount(1, $result->getEntities());
        static::assertNull($result->getUserReview());
    }

    public function testItLoadsReviewsWithParentId(): void
    {
        $reviewId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $request = new Request(['sort' => 'points', 'language' => 'filter-language']);
        $channelContext = $this->getChannelContext();

        $review = $this->getReviewEntity($reviewId);

        $reviews = new BlogReviewCollection([
            $review,
        ]);

        $blogReviewRoute = static::createStub(BlogReviewRoute::class);
        $blogReviewLoader = $this->getBlogReviewLoader($blogReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $channelContext);

        $blogReviewRoute
            ->method('load')
            ->willReturn(
                new BlogReviewRouteResponse($reviewResult)
            );

        $result = $blogReviewLoader->load($request, $channelContext, $blogId);

        static::assertInstanceOf(BlogReviewEntity::class, $result->getEntities()->first());
        static::assertSame($reviewId, $result->getEntities()->first()->getId());
        static::assertCount(1, $result->getEntities());
        static::assertEquals([new FieldSorting('points', 'DESC')], $result->getCriteria()->getSorting());
        static::assertNotNull($result->getUserReview());
    }

    public function testItLoadsReviewsWithPointsFilter(): void
    {
        $reviewId = Uuid::randomHex();
        $blogId = Uuid::randomHex();
        $request = new Request(['points' => ['4', 'gg']]);
        $channelContext = $this->getChannelContext();

        $review = $this->getReviewEntity($reviewId);

        $reviews = new BlogReviewCollection([
            $review,
        ]);

        $blogReviewRoute = static::createStub(BlogReviewRoute::class);
        $blogReviewLoader = $this->getBlogReviewLoader($blogReviewRoute);

        $reviewResult = $this->getDefaultResult($reviews, $request, $channelContext);

        $blogReviewRoute
            ->method('load')
            ->willReturn(
                new BlogReviewRouteResponse($reviewResult)
            );

        $result = $blogReviewLoader->load($request, $channelContext, $blogId);

        static::assertInstanceOf(BlogReviewEntity::class, $result->getEntities()->first());
        static::assertSame($result->getEntities()->first()->getId(), $reviewId);
        static::assertCount(1, $result->getEntities());
    }




    private function getChannelContext(bool $setUser = true): ChannelContext
    {
        $channelEntity = new ChannelEntity();
        $channelEntity->setId('channelId');

        $user = null;

        if ($setUser) {
            $user = new UserEntity();
            $user->setId(Uuid::randomHex());
        }

        return Generator::generateChannelContext(
            channel: $channelEntity,
            overrides: ['user' => $user],
        );
    }

    private function createCriteria(Request $request, ChannelContext $context): Criteria
    {
        $limit = $this->systemConfigService->getInt('core.listing.reviewsPerPage', $context->getChannelId());
        $page = $request->query->getInt('p', 1);
        $offset = max(0, $limit * ($page - 1));

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $sorting = new FieldSorting('createdAt', 'DESC');
        if ($request->query->get('sort', 'createdAt') === 'points') {
            $sorting = new FieldSorting('points', 'DESC');
        }

        $criteria->addSorting($sorting);

        if ($request->query->get('language') === 'filter-language') {
            $criteria->addPostFilter(
                new EqualsFilter('languageId', $context->getLanguageId())
            );
        } else {
            $criteria->addAssociation('language.translationCode.code');
        }

        $reviewFilters = [new EqualsFilter('status', true)];

        if ($context->getUser() !== null) {
            $reviewFilters[] = new EqualsFilter('userId', $context->getUserId());
        }

        $criteria->addAggregation(
            new FilterAggregation(
                'user-login-filter',
                new TermsAggregation('ratingMatrix', 'points'),
                [
                ]
            ),
            new FilterAggregation(
                'language-filter',
                new TermsAggregation('languageMatrix', 'languageId'),
                [
                    new EqualsFilter('languageId', $context->getLanguageId()),
                ]
            )
        );

        return $criteria;
    }
}

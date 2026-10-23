<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Review;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewEntity;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\Garan\AbstractGaranLabelRoute;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\AbstractBlogPurchaseLimitRoute;
use Contena\Core\Content\Blog\Channel\Review\AbstractBlogReviewSaveRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewsWidgetLoadedHook;
use Contena\Core\Content\Blog\Channel\Review\RatingMatrix;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogReviewsWidgetLoadedHook::class)]
class BlogReviewsWidgetLoadedHookTest extends TestCase
{
    private Stub&BlogReviewLoader $blogReviewLoaderMock;

    private BlogControllerStub $controller;

    protected function setUp(): void
    {
        $this->blogReviewLoaderMock = static::createStub(BlogReviewLoader::class);

        $this->controller = new BlogControllerStub(
            static::createStub(BlogPageLoader::class),
            static::createStub(FindBlogVariantRoute::class),
            static::createStub(MinimalQuickViewPageLoader::class),
            static::createStub(AbstractBlogReviewSaveRoute::class),
            static::createStub(SeoUrlPlaceholderHandlerInterface::class),
            $this->blogReviewLoaderMock,
            static::createStub(AbstractBlogPurchaseLimitRoute::class),
            static::createStub(AbstractGaranLabelRoute::class),
        );
    }

    public function testHookTriggeredWhenBlogReviewsWidgetIsLoaded(): void
    {
        $ids = new IdsCollection();

        $blogId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $request = new Request([
            'test' => 'test',
            'blogId' => $blogId,
            'parentId' => $parentId,
        ]);

        $blogReview = new BlogReviewEntity();
        $blogReview->setUniqueIdentifier($ids->get('blogReview'));
        $reviewResult = BlogReviewResult::fromSearchResult(
            new EntitySearchResult(
                'blog_review',
                1,
                new BlogReviewCollection([$blogReview]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
            new RatingMatrix([]),
            $blogId,
            1,
            null,
            $parentId,
        );

        $this->blogReviewLoaderMock->method('load')->willReturn($reviewResult);

        $this->controller->loadReviews(
            $blogId,
            $request,
            static::createStub(ChannelContext::class)
        );

        static::assertInstanceOf(BlogReviewsWidgetLoadedHook::class, $this->controller->recorder()->calledHook);

        $blogReviewsWidgetLoadedHook = $this->controller->recorder()->calledHook;

        static::assertSame($reviewResult, $blogReviewsWidgetLoadedHook->getReviews());
    }
}

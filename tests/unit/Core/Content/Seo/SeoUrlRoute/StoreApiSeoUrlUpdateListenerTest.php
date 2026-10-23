<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Category\Event\CategoryIndexerEvent;
use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\StoreApiSeoUrlUpdateListener;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(StoreApiSeoUrlUpdateListener::class)]
class StoreApiSeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private StoreApiSeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->listener = new StoreApiSeoUrlUpdateListener($this->seoUrlUpdater);
    }

    public function testGetSubscribedEvents(): void
    {
        $this->seoUrlUpdater->expects($this->never())->method('update');

        static::assertSame(
            [
                BlogIndexerEvent::class => 'updateBlogUrls',
                CategoryIndexerEvent::class => 'updateCategoryUrls',
                LandingPageIndexerEvent::class => 'updateLandingPageUrls',
            ],
            StoreApiSeoUrlUpdateListener::getSubscribedEvents()
        );
    }

    public function testUpdateBlogUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $event = new BlogIndexerEvent($ids, Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(BlogStoreApiUrlRoute::ROUTE_NAME, $ids);

        $this->listener->updateBlogUrls($event);
    }

    public function testUpdateBlogUrlsSkipped(): void
    {
        $event = new BlogIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['blog.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateBlogUrls($event);
    }

    public function testUpdateCategoryUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $event = new CategoryIndexerEvent($ids, Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(CategoryStoreApiUrlRoute::ROUTE_NAME, $ids);

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateCategoryUrlsSkipped(): void
    {
        $event = new CategoryIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['category.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateLandingPageUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $event = new LandingPageIndexerEvent($ids, Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(LandingPageStoreApiUrlRoute::ROUTE_NAME, $ids);

        $this->listener->updateLandingPageUrls($event);
    }

    public function testUpdateLandingPageUrlsSkipped(): void
    {
        $event = new LandingPageIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['landing_page.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateLandingPageUrls($event);
    }
}

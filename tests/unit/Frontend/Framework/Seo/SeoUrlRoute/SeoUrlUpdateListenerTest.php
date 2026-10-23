<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Category\Event\CategoryIndexerEvent;
use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;

/**
 * @internal
 */
#[CoversClass(SeoUrlUpdateListener::class)]
class SeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private SeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->listener = new SeoUrlUpdateListener($this->seoUrlUpdater);
    }

    public function testUpdateCategoryUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new CategoryIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                NavigationPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateCategoryUrlsSkipped(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new CategoryIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::CATEGORY_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateCategoryUrls($event);
    }

    public function testUpdateBlogUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new BlogIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                BlogPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateBlogUrls($event);
    }

    public function testUpdateBlogUrlsSkips(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new BlogIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::BLOG_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateBlogUrls($event);
    }

    public function testUpdateLandingPageUrls(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new LandingPageIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext());

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(
                LandingPageSeoUrlRoute::ROUTE_NAME,
                [$parentUuid, $childUuid]
            );

        $this->listener->updateLandingPageUrls($event);
    }

    public function testUpdateLandingPageUrlsSkips(): void
    {
        $childUuid = Uuid::randomHex();
        $parentUuid = Uuid::randomHex();

        $event = new LandingPageIndexerEvent([$parentUuid, $childUuid], Context::createDefaultContext(), [SeoUrlUpdateListener::LANDING_PAGE_SEO_URL_UPDATER]);

        $this->seoUrlUpdater->expects($this->never())
            ->method('update');

        $this->listener->updateLandingPageUrls($event);
    }
}

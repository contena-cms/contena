<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Category\Event\CategoryIndexerEvent;
use Contena\Core\Content\LandingPage\Event\LandingPageIndexerEvent;
use Contena\Core\Content\Seo\SeoUrlRoute\BlogChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\CategoryChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlRoute\ChannelApiSeoUrlUpdateListener;
use Contena\Core\Content\Seo\SeoUrlRoute\LandingPageChannelApiUrlRoute;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ChannelApiSeoUrlUpdateListener::class)]
class ChannelApiSeoUrlUpdateListenerTest extends TestCase
{
    private SeoUrlUpdater&MockObject $seoUrlUpdater;

    private ChannelApiSeoUrlUpdateListener $listener;

    protected function setUp(): void
    {
        $this->seoUrlUpdater = $this->createMock(SeoUrlUpdater::class);
        $this->listener = new ChannelApiSeoUrlUpdateListener($this->seoUrlUpdater);
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
            ChannelApiSeoUrlUpdateListener::getSubscribedEvents()
        );
    }

    public function testUpdateBlogUrls(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex()];
        $context = Context::createDefaultContext();
        $event = new BlogIndexerEvent($ids, $context);

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(BlogChannelApiUrlRoute::ROUTE_NAME, $ids, $context);

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
        $context = Context::createDefaultContext();
        $event = new CategoryIndexerEvent($ids, $context);

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(CategoryChannelApiUrlRoute::ROUTE_NAME, $ids, $context);

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
        $context = Context::createDefaultContext();
        $event = new LandingPageIndexerEvent($ids, $context);

        $this->seoUrlUpdater->expects($this->once())
            ->method('update')
            ->with(LandingPageChannelApiUrlRoute::ROUTE_NAME, $ids, $context);

        $this->listener->updateLandingPageUrls($event);
    }

    public function testUpdateLandingPageUrlsSkipped(): void
    {
        $event = new LandingPageIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), ['landing_page.seo-url']);

        $this->seoUrlUpdater->expects($this->never())->method('update');

        $this->listener->updateLandingPageUrls($event);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Seo\Hreflang\HreflangCollection;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\Content\Seo\HreflangLoaderParameter;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Contena\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Contena\Core\Framework\App\ShopId\ShopId;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\Test\Generator;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Framework\Routing\TemplateDataSubscriber;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\Blog\BlogPage;
use Contena\Frontend\Theme\ThemeRuntimeConfig;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(TemplateDataSubscriber::class)]
class TemplateDataSubscriberTest extends TestCase
{
    private HreflangLoaderInterface&MockObject $hreflangLoader;

    private ShopIdProvider&Stub $shopIdProvider;

    private ActiveAppsLoader&Stub $activeAppsLoader;

    private TemplateDataSubscriber $subscriber;

    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    protected function setUp(): void
    {
        $this->hreflangLoader = static::createMock(HreflangLoaderInterface::class);
        $this->shopIdProvider = static::createStub(ShopIdProvider::class);
        $this->activeAppsLoader = static::createStub(ActiveAppsLoader::class);
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);

        $this->subscriber = $this->buildSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $events = TemplateDataSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(FrontendRenderEvent::class, $events);

        static::assertArrayHasKey(FrontendRenderEvent::class, $events);
        static::assertIsArray($events[FrontendRenderEvent::class]);
        static::assertCount(3, $events[FrontendRenderEvent::class]);

        static::assertArrayHasKey('0', $events[FrontendRenderEvent::class]);
        static::assertIsArray($events[FrontendRenderEvent::class][0]);
        static::assertArrayHasKey('0', $events[FrontendRenderEvent::class][0]);
        static::assertSame('addHreflang', $events[FrontendRenderEvent::class][0][0]);

        static::assertArrayHasKey('1', $events[FrontendRenderEvent::class]);
        static::assertIsArray($events[FrontendRenderEvent::class][1]);
        static::assertSame('addShopIdParameter', $events[FrontendRenderEvent::class][1][0]);

        static::assertArrayHasKey('2', $events[FrontendRenderEvent::class]);
        static::assertIsArray($events[FrontendRenderEvent::class][2]);
        static::assertSame('addIconSetConfig', $events[FrontendRenderEvent::class][2][0]);
    }

    public function testAddHreflangWithNullRoute(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new FrontendRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateChannelContext()
        );

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->never())->method('load');

        $subscriber = $this->buildSubscriber(hreflangLoader: $hreflangLoader);

        $subscriber->addHreflang($event);
    }

    public function testAddHreflangSkippedForEsiRequest(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set('_route', 'frontend.header');
        $request->attributes->set('_esi', true);

        $event = new FrontendRenderEvent(
            'test',
            [],
            $request,
            Generator::generateChannelContext()
        );

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->never())->method('load');

        $subscriber = $this->buildSubscriber(hreflangLoader: $hreflangLoader);

        $subscriber->addHreflang($event);
    }

    public function testAddHreflangWithValidRoute(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set('_route', 'frontend.home');
        $request->attributes->set('_route_params', ['param' => 'value']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());

        $event = new FrontendRenderEvent(
            'test',
            [],
            $request,
            Generator::generateChannelContext()
        );

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn(new HreflangCollection());

        $subscriber = $this->buildSubscriber(hreflangLoader: $hreflangLoader);

        $subscriber->addHreflang($event);

        static::assertInstanceOf(HreflangCollection::class, $event->getParameters()['hrefLang']);
    }

    public function testAddHreflangUsesCanonicalBlogIdWhenSet(): void
    {
        $variantBlogId = 'variant-blog-id';
        $canonicalBlogId = 'canonical-blog-id';

        $request = new Request();
        $request->attributes->set('_route', BlogPageSeoUrlRoute::ROUTE_NAME);
        $request->attributes->set('_route_params', ['blogId' => $variantBlogId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());

        $blog = new ChannelBlogEntity();
        $blog->setCanonicalBlogId($canonicalBlogId);

        $page = new BlogPage();
        $page->setBlog($blog);

        $event = new FrontendRenderEvent(
            'test',
            ['page' => $page],
            $request,
            Generator::generateChannelContext()
        );

        $this->hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (HreflangLoaderParameter $parameter) use ($canonicalBlogId): bool {
                return $parameter->getRouteParameters()['blogId'] === $canonicalBlogId;
            }))
            ->willReturn(new HreflangCollection());

        $this->subscriber->addHreflang($event);
    }

    public function testAddHreflangUsesBlogIdWhenNoCanonicalBlogId(): void
    {
        $blogId = 'parent-blog-id';

        $request = new Request();
        $request->attributes->set('_route', BlogPageSeoUrlRoute::ROUTE_NAME);
        $request->attributes->set('_route_params', ['blogId' => $blogId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());

        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        $page = new BlogPage();
        $page->setBlog($blog);

        $event = new FrontendRenderEvent(
            'test',
            ['page' => $page],
            $request,
            Generator::generateChannelContext()
        );

        $this->hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (HreflangLoaderParameter $parameter) use ($blogId): bool {
                return $parameter->getRouteParameters()['blogId'] === $blogId;
            }))
            ->willReturn(new HreflangCollection());

        $this->subscriber->addHreflang($event);
    }

    public function testAddHreflangUsesVariantBlogIdWhenParentRouteIsUsed(): void
    {
        $parentBlogId = 'parent-blog-id';
        $variantBlogId = 'parent-blog-id';

        $request = new Request();
        $request->attributes->set('_route', BlogPageSeoUrlRoute::ROUTE_NAME);
        $request->attributes->set('_route_params', ['blogId' => $parentBlogId]);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, Generator::generateChannelContext());

        $blog = new ChannelBlogEntity();
        $blog->setId($variantBlogId);

        $page = new BlogPage();
        $page->setBlog($blog);

        $event = new FrontendRenderEvent(
            'test',
            ['page' => $page],
            $request,
            Generator::generateChannelContext()
        );

        $this->hreflangLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::callback(static function (HreflangLoaderParameter $parameter) use ($variantBlogId): bool {
                return $parameter->getRouteParameters()['blogId'] === $variantBlogId;
            }))
            ->willReturn(new HreflangCollection());

        $this->subscriber->addHreflang($event);
    }

    public function testAddShopIdParameterWithNoActiveApps(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new FrontendRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateChannelContext()
        );

        $this->activeAppsLoader
            ->method('getActiveApps')
            ->willReturn([]);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->never())
            ->method('getShopId');

        $subscriber = $this->buildSubscriber(shopIdProvider: $shopIdProvider);

        $subscriber->addShopIdParameter($event);
    }

    public function testAddShopIdParameterWithUrlChangeException(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new FrontendRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateChannelContext()
        );

        $this->activeAppsLoader
            ->method('getActiveApps')
            ->willReturn(['someApp']);

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new ShopIdChangeSuggestedException(ShopId::v2('123'), new FingerprintComparisonResult([], [], 75)));

        $subscriber = $this->buildSubscriber(shopIdProvider: $shopIdProvider);

        $subscriber->addShopIdParameter($event);
    }

    public function testShopIdAdded(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new FrontendRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateChannelContext()
        );

        $this->activeAppsLoader
            ->method('getActiveApps')
            ->willReturn(['someApp']);

        $shopId = ShopId::v2('123');
        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willReturn($shopId);

        $subscriber = $this->buildSubscriber(shopIdProvider: $shopIdProvider);

        $subscriber->addShopIdParameter($event);

        static::assertSame('123', $event->getParameters()['appShopId']);
    }

    public function testAddIconSetConfigWithNoTheme(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $event = new FrontendRenderEvent(
            'test',
            [],
            new Request(),
            Generator::generateChannelContext()
        );

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService
            ->expects($this->never())
            ->method('getRuntimeConfigByName');

        $subscriber = $this->buildSubscriber(themeRuntimeConfigService: $themeRuntimeConfigService);

        $subscriber->addIconSetConfig($event);
    }

    public function testAddIconSetConfigWithNoThemeButThemeName(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'Frontend');

        $event = new FrontendRenderEvent(
            'test',
            [],
            $request,
            Generator::generateChannelContext()
        );

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService
            ->expects($this->once())
            ->method('getRuntimeConfigByName');

        $subscriber = $this->buildSubscriber(themeRuntimeConfigService: $themeRuntimeConfigService);

        $subscriber->addIconSetConfig($event);
        static::assertArrayNotHasKey('themeIconConfig', $event->getParameters());
    }

    public function testAddIconSetConfigWithValidTheme(): void
    {
        $this->hreflangLoader->expects($this->never())->method('load');

        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'Frontend');

        $event = new FrontendRenderEvent(
            'test',
            [],
            $request,
            Generator::generateChannelContext()
        );

        $themeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => '123',
            'technicalName' => 'Frontend',
            'resolvedConfig' => [],
            'iconSets' => ['default' => ['path' => '@Frontend/icons/default', 'namespace' => '']],
            'updatedAt' => new \DateTime(),
        ]);

        $this->themeRuntimeConfigService
            ->method('getRuntimeConfigByName')
            ->willReturn($themeConfig);

        $this->subscriber->addIconSetConfig($event);

        static::assertArrayHasKey('themeIconConfig', $event->getParameters());
        static::assertSame($themeConfig->iconSets, $event->getParameters()['themeIconConfig']);
    }

    private function buildSubscriber(
        ?HreflangLoaderInterface $hreflangLoader = null,
        ?ShopIdProvider $shopIdProvider = null,
        ?ActiveAppsLoader $activeAppsLoader = null,
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
    ): TemplateDataSubscriber {
        return new TemplateDataSubscriber(
            $hreflangLoader ?? $this->hreflangLoader,
            $shopIdProvider ?? $this->shopIdProvider,
            $activeAppsLoader ?? $this->activeAppsLoader,
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
        );
    }
}

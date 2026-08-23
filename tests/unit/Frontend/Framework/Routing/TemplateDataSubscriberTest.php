<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Content\Seo\Hreflang\HreflangCollection;
use Contena\Core\Content\Seo\HreflangLoaderInterface;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Generator;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Framework\Routing\TemplateDataSubscriber;
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

    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    private TemplateDataSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->hreflangLoader = static::createMock(HreflangLoaderInterface::class);
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $this->subscriber = $this->buildSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = TemplateDataSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(FrontendRenderEvent::class, $events);
        static::assertIsArray($events[FrontendRenderEvent::class]);
        static::assertCount(2, $events[FrontendRenderEvent::class]);
        static::assertIsArray($events[FrontendRenderEvent::class][0]);
        static::assertIsArray($events[FrontendRenderEvent::class][1]);
        static::assertSame('addHreflang', $events[FrontendRenderEvent::class][0][0]);
        static::assertSame('addIconSetConfig', $events[FrontendRenderEvent::class][1][0]);
    }

    public function testAddHreflangWithNullRoute(): void
    {
        $event = new FrontendRenderEvent('test', [], new Request(), Generator::generateChannelContext());
        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->never())->method('load');

        $this->buildSubscriber(hreflangLoader: $hreflangLoader)->addHreflang($event);
    }

    public function testAddHreflangSkippedForEsiRequest(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.header');
        $request->attributes->set('_esi', true);

        $event = new FrontendRenderEvent('test', [], $request, Generator::generateChannelContext());
        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->never())->method('load');

        $this->buildSubscriber(hreflangLoader: $hreflangLoader)->addHreflang($event);
    }

    public function testAddHreflangWithValidRoute(): void
    {
        $context = Generator::generateChannelContext();
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home');
        $request->attributes->set('_route_params', ['param' => 'value']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $event = new FrontendRenderEvent('test', [], $request, $context);

        $hreflangLoader = $this->createMock(HreflangLoaderInterface::class);
        $hreflangLoader->expects($this->once())->method('load')->willReturn(new HreflangCollection());

        $this->buildSubscriber(hreflangLoader: $hreflangLoader)->addHreflang($event);

        static::assertInstanceOf(HreflangCollection::class, $event->getParameters()['hrefLang']);
    }

    public function testAddIconSetConfigWithNoTheme(): void
    {
        $event = new FrontendRenderEvent('test', [], new Request(), Generator::generateChannelContext());
        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->never())->method('getRuntimeConfigByName');

        $this->buildSubscriber(themeRuntimeConfigService: $themeRuntimeConfigService)->addIconSetConfig($event);
    }

    public function testAddIconSetConfigWithNoThemeButThemeName(): void
    {
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'Frontend');
        $event = new FrontendRenderEvent('test', [], $request, Generator::generateChannelContext());

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())->method('getRuntimeConfigByName');

        $this->buildSubscriber(themeRuntimeConfigService: $themeRuntimeConfigService)->addIconSetConfig($event);
        static::assertArrayNotHasKey('themeIconConfig', $event->getParameters());
    }

    public function testAddIconSetConfigWithValidTheme(): void
    {
        $request = new Request();
        $request->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, 'Frontend');
        $event = new FrontendRenderEvent('test', [], $request, Generator::generateChannelContext());

        $themeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => '123',
            'technicalName' => 'Frontend',
            'resolvedConfig' => [],
            'iconSets' => ['default' => ['path' => '@Frontend/icons/default', 'namespace' => '']],
            'updatedAt' => new \DateTime(),
        ]);

        $this->themeRuntimeConfigService->method('getRuntimeConfigByName')->willReturn($themeConfig);
        $this->subscriber->addIconSetConfig($event);

        static::assertArrayHasKey('themeIconConfig', $event->getParameters());
        static::assertSame($themeConfig->iconSets, $event->getParameters()['themeIconConfig']);
    }

    private function buildSubscriber(
        ?HreflangLoaderInterface $hreflangLoader = null,
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
    ): TemplateDataSubscriber {
        return new TemplateDataSubscriber(
            $hreflangLoader ?? $this->hreflangLoader,
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
        );
    }
}

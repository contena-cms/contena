<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Snippet\Event\SnippetsThemeResolveEvent;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Subscriber\ThemeSnippetsSubscriber;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;

/**
 * @internal
 */
#[CoversClass(ThemeSnippetsSubscriber::class)]
class ThemeSnippetsSubscriberTest extends TestCase
{
    private ThemeRuntimeConfigService&Stub $themeRuntimeConfigService;

    private DatabaseChannelThemeLoader&Stub $channelThemeLoader;

    protected function setUp(): void
    {
        $this->themeRuntimeConfigService = static::createStub(ThemeRuntimeConfigService::class);
        $this->channelThemeLoader = static::createStub(DatabaseChannelThemeLoader::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ThemeSnippetsSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(SnippetsThemeResolveEvent::class, $events);
        static::assertSame('onSnippetsThemeResolve', $events[SnippetsThemeResolveEvent::class]);
    }

    public function testOnSnippetsThemeResolveWithChannel(): void
    {
        $channelId = 'test-channel';
        $event = new SnippetsThemeResolveEvent($channelId);

        $usedThemes = ['theme1', 'theme2'];
        $allThemes = ['theme1', 'theme2', 'theme3', 'theme4'];

        $channelThemeLoader = $this->createMock(DatabaseChannelThemeLoader::class);
        $channelThemeLoader->expects($this->once())
            ->method('load')
            ->with($channelId)
            ->willReturn($usedThemes);

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($allThemes);

        $subscriber = $this->createSubscriber($themeRuntimeConfigService, $channelThemeLoader);
        $subscriber->onSnippetsThemeResolve($event);

        static::assertSame(
            ['theme1', 'theme2', FrontendPluginRegistry::BASE_THEME_NAME],
            $event->getUsedThemes()
        );

        static::assertEquals(
            ['theme3', 'theme4'],
            $event->getUnusedThemes()
        );
    }

    public function testOnSnippetsThemeResolveWithoutChannel(): void
    {
        $event = new SnippetsThemeResolveEvent(null);

        $allThemes = ['theme1', 'theme2', 'theme3', 'theme4'];

        $channelThemeLoader = $this->createMock(DatabaseChannelThemeLoader::class);
        $channelThemeLoader->expects($this->never())
            ->method('load');

        $themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $themeRuntimeConfigService->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($allThemes);

        $subscriber = $this->createSubscriber($themeRuntimeConfigService, $channelThemeLoader);
        $subscriber->onSnippetsThemeResolve($event);

        static::assertSame(
            [FrontendPluginRegistry::BASE_THEME_NAME],
            $event->getUsedThemes()
        );

        static::assertSame(
            $allThemes,
            $event->getUnusedThemes()
        );
    }

    private function createSubscriber(
        ?ThemeRuntimeConfigService $themeRuntimeConfigService = null,
        ?DatabaseChannelThemeLoader $channelThemeLoader = null
    ): ThemeSnippetsSubscriber {
        return new ThemeSnippetsSubscriber(
            $themeRuntimeConfigService ?? $this->themeRuntimeConfigService,
            $channelThemeLoader ?? $this->channelThemeLoader
        );
    }
}

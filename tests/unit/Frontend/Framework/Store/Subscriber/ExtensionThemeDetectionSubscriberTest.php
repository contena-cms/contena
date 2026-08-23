<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Store\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Store\Event\ExtensionLoadedEvent;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;
use Contena\Frontend\Framework\Store\Subscriber\ExtensionThemeDetectionSubscriber;
use Contena\Tests\Unit\Frontend\Theme\fixtures\MockFrontend\MockFrontend;

/**
 * @internal
 */
#[CoversClass(ExtensionThemeDetectionSubscriber::class)]
class ExtensionThemeDetectionSubscriberTest extends TestCase
{
    #[TestDox('Subscribes to the single extension-loaded event')]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ExtensionLoadedEvent::class => 'detectTheme',
            ],
            ExtensionThemeDetectionSubscriber::getSubscribedEvents(),
        );
    }

    #[TestDox('A plugin whose base class implements ThemeInterface is flagged as theme')]
    public function testDetectThemeFlagsThemePlugin(): void
    {
        $plugin = new PluginEntity();
        $plugin->assign(['baseClass' => MockFrontend::class]);

        $extension = ExtensionStruct::fromArray(['name' => 'theme', 'label' => 'Theme', 'type' => 'plugin']);
        new ExtensionThemeDetectionSubscriber()->detectTheme(
            new ExtensionLoadedEvent($plugin, $extension, Context::createDefaultContext()),
        );

        static::assertTrue($extension->isTheme());
    }

    #[TestDox('A plugin leaves isTheme false: $_dataName')]
    #[DataProvider('nonThemePluginBaseClassProvider')]
    public function testDetectThemeLeavesNonThemePluginUntouched(string $baseClass): void
    {
        $plugin = new PluginEntity();
        $plugin->assign(['baseClass' => $baseClass]);

        $extension = ExtensionStruct::fromArray(['name' => 'plugin', 'label' => 'Plugin', 'type' => 'plugin']);
        new ExtensionThemeDetectionSubscriber()->detectTheme(
            new ExtensionLoadedEvent($plugin, $extension, Context::createDefaultContext()),
        );

        static::assertFalse($extension->isTheme());
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function nonThemePluginBaseClassProvider(): \Generator
    {
        yield 'non-existent class' => ['NonExistent\\Class\\Name'];
        yield 'existing class that does not implement ThemeInterface' => [\stdClass::class];
    }
}

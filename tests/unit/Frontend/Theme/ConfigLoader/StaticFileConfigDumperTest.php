<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Frontend\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigDumper;
use Contena\Frontend\Theme\Event\ThemeConfigChangedEvent;
use Contena\Frontend\Theme\Event\ThemeConfigResetEvent;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;

/**
 * @internal
 */
#[CoversClass(StaticFileConfigDumper::class)]
class StaticFileConfigDumperTest extends TestCase
{
    public function testDumping(): void
    {
        $configuration = new FrontendPluginConfiguration('Test');
        $loader = static::createStub(AbstractConfigLoader::class);
        $loader->method('load')->willReturn($configuration);

        $privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFilesystem = new Filesystem(new InMemoryFilesystemAdapter());

        $themeProvider = static::createStub(AbstractAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        $dumper = new StaticFileConfigDumper($loader, $themeProvider, $privateFilesystem, $temporaryFilesystem);

        $dumper->dumpConfig(Context::createDefaultContext());
        static::assertSame('{"test":"test"}', $privateFilesystem->read(StaticFileAvailableThemeProvider::THEME_INDEX));

        $dumper->dumpConfigFromEvent();
        static::assertSame('{"test":"test"}', $privateFilesystem->read(StaticFileAvailableThemeProvider::THEME_INDEX));
    }

    public function testDumpConfigInVar(): void
    {
        $temporaryFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $dumper = new StaticFileConfigDumper(
            static::createStub(AbstractConfigLoader::class),
            static::createStub(AbstractAvailableThemeProvider::class),
            new Filesystem(new InMemoryFilesystemAdapter()),
            $temporaryFilesystem
        );

        $dumper->dumpConfigInVar('theme-files.json', ['test' => '123']);
        static::assertJsonStringEqualsJsonString('{"test": "123"}', $temporaryFilesystem->read('theme-files.json'));
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ThemeConfigChangedEvent::class => 'dumpConfigFromEvent',
            ThemeConfigResetEvent::class => 'dumpConfigFromEvent',
        ], StaticFileConfigDumper::getSubscribedEvents());
    }

    public function testDumpConfigCreatesDirectoryIfNotExists(): void
    {
        $loader = static::createStub(AbstractConfigLoader::class);
        $loader->method('load')->willReturn(new FrontendPluginConfiguration('Test'));

        $privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $themeProvider = static::createStub(AbstractAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        static::assertFalse($privateFilesystem->directoryExists('theme-config'));

        new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $privateFilesystem,
            new Filesystem(new InMemoryFilesystemAdapter())
        )->dumpConfig(Context::createDefaultContext());

        static::assertTrue($privateFilesystem->directoryExists('theme-config'));
        static::assertTrue($privateFilesystem->fileExists(StaticFileAvailableThemeProvider::THEME_INDEX));
        static::assertTrue($privateFilesystem->fileExists('theme-config/test.json'));
    }
}

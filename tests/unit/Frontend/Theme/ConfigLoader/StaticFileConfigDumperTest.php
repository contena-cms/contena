<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigDumper;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;
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
        $channelToTheme = new FrontendPluginConfiguration('Test');
        $loader = static::createStub(DatabaseConfigLoader::class);
        $loader->method('load')->willReturn($channelToTheme);

        $privateFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $themeProvider = static::createStub(DatabaseAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        $dumper = new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $privateFileSystem,
            $temporaryFileSystem
        );

        $location = StaticFileAvailableThemeProvider::THEME_INDEX;

        $dumper->dumpConfig(Context::createDefaultContext());
        static::assertSame('{"test":"test"}', $privateFileSystem->read($location));

        $dumper->dumpConfigFromEvent();
        static::assertSame('{"test":"test"}', $privateFileSystem->read($location));
    }

    public function testDumpConfigInVar(): void
    {
        $privateFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $dumper = new StaticFileConfigDumper(
            static::createStub(DatabaseConfigLoader::class),
            static::createStub(DatabaseAvailableThemeProvider::class),
            $privateFileSystem,
            $temporaryFileSystem
        );

        $location = 'theme-files.json';
        $dump = ['test' => '123'];

        $dumper->dumpConfigInVar($location, $dump);
        static::assertJsonStringEqualsJsonString('{"test": "123"}', $temporaryFileSystem->read($location));
    }

    public function testgetSubscribedEvents(): void
    {
        static::assertSame(
            [
                ThemeConfigChangedEvent::class => 'dumpConfigFromEvent',
                ThemeAssignedEvent::class => 'dumpConfigFromEvent',
                ThemeConfigResetEvent::class => 'dumpConfigFromEvent',
            ],
            StaticFileConfigDumper::getSubscribedEvents()
        );
    }

    public function testDumpConfigCreatesDirectoryIfNotExists(): void
    {
        $channelToTheme = new FrontendPluginConfiguration('Test');
        $loader = static::createStub(DatabaseConfigLoader::class);
        $loader->method('load')->willReturn($channelToTheme);

        $privateFileSystem = new Filesystem(new InMemoryFilesystemAdapter());
        $temporaryFileSystem = new Filesystem(new InMemoryFilesystemAdapter());

        $themeProvider = static::createStub(DatabaseAvailableThemeProvider::class);
        $themeProvider->method('load')->willReturn(['test' => 'test']);

        // Verify directory doesn't exist initially
        static::assertFalse($privateFileSystem->directoryExists('theme-config'));

        $dumper = new StaticFileConfigDumper(
            $loader,
            $themeProvider,
            $privateFileSystem,
            $temporaryFileSystem
        );

        $dumper->dumpConfig(Context::createDefaultContext());

        // Verify directory was created
        static::assertTrue($privateFileSystem->directoryExists('theme-config'));
        // Verify index file was created
        static::assertTrue($privateFileSystem->fileExists(StaticFileAvailableThemeProvider::THEME_INDEX));
        // Verify theme config file was created
        static::assertTrue($privateFileSystem->fileExists('theme-config/test.json'));
    }
}

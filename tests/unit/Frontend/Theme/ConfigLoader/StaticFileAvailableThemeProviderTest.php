<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Frontend\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Contena\Frontend\Theme\Exception\ThemeException;

/**
 * @internal
 */
#[CoversClass(StaticFileAvailableThemeProvider::class)]
class StaticFileAvailableThemeProviderTest extends TestCase
{
    public function testFileNotExisting(): void
    {
        $this->expectExceptionObject(ThemeException::configNotFound(StaticFileAvailableThemeProvider::THEME_INDEX));

        $provider = new StaticFileAvailableThemeProvider(new Filesystem(new InMemoryFilesystemAdapter()));
        $provider->load(Context::createDefaultContext(), false);
    }

    public function testFileExists(): void
    {
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $filesystem->write(StaticFileAvailableThemeProvider::THEME_INDEX, json_encode(['test' => 'test'], \JSON_THROW_ON_ERROR));

        $provider = new StaticFileAvailableThemeProvider($filesystem);
        static::assertSame(['test' => 'test'], $provider->load(Context::createDefaultContext(), false));
    }

    public function testCallGetDecoratedThrowsError(): void
    {
        static::expectException(DecorationPatternException::class);

        $provider = new StaticFileAvailableThemeProvider(new Filesystem(new InMemoryFilesystemAdapter()));
        $provider->getDecorated();
    }
}

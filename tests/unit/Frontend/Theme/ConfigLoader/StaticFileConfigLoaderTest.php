<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\ConfigLoader;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigLoader;
use Contena\Frontend\Theme\Exception\ThemeException;

/**
 * @internal
 */
#[CoversClass(StaticFileConfigLoader::class)]
class StaticFileConfigLoaderTest extends TestCase
{
    public function testFileNotExisting(): void
    {
        $id = Uuid::randomHex();
        $this->expectExceptionObject(ThemeException::configNotFound('theme-config/' . $id . '.json'));

        $loader = new StaticFileConfigLoader(new Filesystem(new InMemoryFilesystemAdapter()));
        $loader->load($id, Context::createDefaultContext());
    }

    public function testBuild(): void
    {
        $id = Uuid::randomHex();
        $filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $fixture = file_get_contents(__DIR__ . '/../fixtures/ConfigLoader/theme-config.json');
        static::assertIsString($fixture);
        $filesystem->write('theme-config/' . $id . '.json', $fixture);

        $config = new StaticFileConfigLoader($filesystem)->load($id, Context::createDefaultContext());

        $themeConfig = $config->getThemeConfig();
        static::assertIsArray($themeConfig);
        static::assertSame(['blocks', 'fields'], array_keys($themeConfig));
        static::assertSame(['contena-color-brand-primary'], array_keys($themeConfig['fields']));
    }

    public function testCallGetDecoratedThrowsError(): void
    {
        static::expectException(DecorationPatternException::class);

        $loader = new StaticFileConfigLoader(new Filesystem(new InMemoryFilesystemAdapter()));
        $loader->getDecorated();
    }
}

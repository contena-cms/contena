<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Util\Filesystem;
use Contena\Core\Test\Stub\App\StaticSourceResolver;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontPluginConfigurationFactory::class)]
class StorefrontPluginConfigurationFactoryTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver([]),
            new SymfonyFilesystem(),
        );

        $this->expectException(DecorationPatternException::class);
        $configurationFactory->getDecorated();
    }

    public function testFactorySetsConfiguration(): void
    {
        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver([]),
            new SymfonyFilesystem(),
        );

        $themePluginBundle = new TestTheme();

        $config = $configurationFactory->createFromBundle($themePluginBundle);

        static::assertSame('TestTheme', $config->getName());
        static::assertSame(
            [
                'name' => 'TestTheme',
                'author' => 'Contena AG',
                'views' => [
                    '@Storefront',
                    '@Plugins',
                    '@TestTheme',
                ],
                'style' => [
                    'app/storefront/src/scss/overrides.scss',
                    '@Storefront',
                    'app/storefront/src/scss/base.scss',
                ],
                'script' => [
                    '@Storefront',
                    'app/storefront/dist/storefront/js/test-theme/test-theme.js',
                ],
                'asset' => [],
            ],
            $config->getThemeJson()
        );
        static::assertSame([], $config->getThemeConfig());
        static::assertTrue($config->getIsTheme());
        static::assertCount(3, $config->getStyleFiles());
        static::assertCount(2, $config->getScriptFiles());
        static::assertFalse($config->hasAdditionalBundles());
    }

    public function testFactorySetsConfigurationWithAdditionalBundles(): void
    {
        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver([]),
            new SymfonyFilesystem(),
        );

        $PluginSubBundle = new PluginWithAdditionalBundles(true, '');

        $config = $configurationFactory->createFromBundle($PluginSubBundle);

        static::assertTrue($config->hasAdditionalBundles());
    }

    public function testFactorySetsConfigurationWithAppSource(): void
    {
        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver(['test' => new Filesystem(__DIR__ . '/fixtures/Apps/test')]),
            new SymfonyFilesystem(),
        );

        $config = $configurationFactory->createFromApp('test', __DIR__ . '/fixtures/Apps/test');

        static::assertFalse($config->getIsTheme());
    }

    public function testFactorySetsConfigurationWithAppSourceAsTheme(): void
    {
        $configurationFactory = new StorefrontPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver(['SwagTheme' => new Filesystem(__DIR__ . '/fixtures/Apps/theme')]),
            new SymfonyFilesystem(),
        );

        $config = $configurationFactory->createFromApp('SwagTheme', __DIR__ . '/fixtures/Apps/theme');

        static::assertTrue($config->getIsTheme());
    }
}

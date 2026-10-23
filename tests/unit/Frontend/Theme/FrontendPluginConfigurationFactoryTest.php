<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Util\Filesystem;
use Contena\Core\Test\Stub\App\StaticSourceResolver;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[CoversClass(FrontendPluginConfigurationFactory::class)]
class FrontendPluginConfigurationFactoryTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        $configurationFactory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver([]),
            new SymfonyFilesystem(),
        );

        $this->expectException(DecorationPatternException::class);
        $configurationFactory->getDecorated();
    }

    public function testFactorySetsConfiguration(): void
    {
        $configurationFactory = new FrontendPluginConfigurationFactory(
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
                'author' => 'Contena',
                'views' => [
                    '@Frontend',
                    '@Plugins',
                    '@TestTheme',
                ],
                'style' => [
                    'app/frontend/src/scss/overrides.scss',
                    '@Frontend',
                    'app/frontend/src/scss/base.scss',
                ],
                'script' => [
                    '@Frontend',
                    'app/frontend/dist/frontend/js/test-theme/test-theme.js',
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
        $configurationFactory = new FrontendPluginConfigurationFactory(
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
        $configurationFactory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver(['test' => new Filesystem(__DIR__ . '/fixtures/Apps/test')]),
            new SymfonyFilesystem(),
        );

        $config = $configurationFactory->createFromApp('test', __DIR__ . '/fixtures/Apps/test');

        static::assertFalse($config->getIsTheme());
    }

    public function testFactorySetsConfigurationWithAppSourceAsTheme(): void
    {
        $configurationFactory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new StaticSourceResolver(['SwagTheme' => new Filesystem(__DIR__ . '/fixtures/Apps/theme')]),
            new SymfonyFilesystem(),
        );

        $config = $configurationFactory->createFromApp('SwagTheme', __DIR__ . '/fixtures/Apps/theme');

        static::assertTrue($config->getIsTheme());
    }
}

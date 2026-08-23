<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Frontend\Framework\ThemeInterface;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Tests\Unit\Frontend\Theme\fixtures\PluginWithAdditionalBundles\PluginWithAdditionalBundles;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(FrontendPluginConfigurationFactory::class)]
class FrontendPluginConfigurationFactoryTest extends TestCase
{
    private FrontendPluginConfigurationFactory $configurationFactory;

    protected function setUp(): void
    {
        $this->configurationFactory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->configurationFactory->getDecorated();
    }

    public function testFactorySetsConfiguration(): void
    {
        $config = $this->configurationFactory->createFromBundle(new TestTheme());

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
        static::assertEmpty($config->getThemeConfig());
        static::assertTrue($config->getIsTheme());
        static::assertCount(3, $config->getStyleFiles());
        static::assertCount(2, $config->getScriptFiles());
        static::assertFalse($config->hasAdditionalBundles());
    }

    public function testFactorySetsConfigurationWithAdditionalBundles(): void
    {
        $pluginSubBundle = new PluginWithAdditionalBundles(true, '');

        $config = $this->configurationFactory->createFromBundle($pluginSubBundle);

        static::assertTrue($config->hasAdditionalBundles());
    }

    public function testCreateThemeConfig(): void
    {
        $basePath = realpath(__DIR__ . '/fixtures/ThemeConfig');
        static::assertIsString($basePath);

        $theme = $this->getBundle('TestTheme', $basePath, true);
        $config = $this->configurationFactory->createFromBundle($theme);

        static::assertSame('TestTheme', $config->getTechnicalName());
        static::assertTrue($config->getIsTheme());
        static::assertSame(
            'app/frontend/src/main.js',
            $config->getFrontendEntryFilepath()
        );
        $this->assertFileCollection([
            'app/frontend/src/scss/overrides.scss' => [],
            '@Frontend' => [],
            'app/frontend/src/scss/base.scss' => [
                'vendor' => 'app/frontend/vendor',
            ],
        ], $config->getStyleFiles());
        $this->assertFileCollection([
            '@Frontend' => [],
            'app/frontend/dist/js/main.js' => [],
        ], $config->getScriptFiles());
        static::assertSame([
            '@Frontend',
            '@Plugins',
            '@ContenaTheme',
        ], $config->getViewInheritance());
        static::assertSame(['app/frontend/dist/assets'], $config->getAssetPaths());
        static::assertSame('app/frontend/dist/assets/preview.jpg', $config->getPreviewMedia());
        static::assertSame([
            'fields' => [
                'contena-image' => [
                    'type' => 'media',
                    'value' => 'app/frontend/dist/assets/test.jpg',
                ],
            ],
        ], $config->getThemeConfig());
        static::assertSame([
            'custom-icons' => 'app/frontend/src/assets/icon-pack/custom-icons',
        ], $config->getIconSets());
    }

    public function testPluginHasSingleScssEntryPoint(): void
    {
        $basePath = realpath(__DIR__ . '/fixtures/SimplePlugin');
        static::assertIsString($basePath);
        $bundle = $this->getBundle('SimplePlugin', $basePath);

        $config = $this->configurationFactory->createFromBundle($bundle);

        $this->assertFileCollection(['app/frontend/src/scss/base.scss' => []], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPoint(): void
    {
        $basePath = realpath(__DIR__ . '/fixtures/SimplePluginWithoutCompilation');
        static::assertIsString($basePath);

        $bundle = $this->getBundle('SimplePluginWithoutCompilation', $basePath);
        $config = $this->configurationFactory->createFromBundle($bundle);

        $this->assertFileCollection([], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPointButDifferentScssFiles(): void
    {
        $basePath = realpath(__DIR__ . '/fixtures/SimpleWithoutStyleEntryPoint');
        static::assertIsString($basePath);

        $bundle = $this->getBundle('SimpleWithoutStyleEntryPoint', $basePath);

        $config = $this->configurationFactory->createFromBundle($bundle);

        // Style files should still be empty because of missing base.scss
        $this->assertFileCollection([], $config->getStyleFiles());
    }

    private function getBundle(string $name, string $basePath, bool $isTheme = false): Bundle
    {
        if ($isTheme) {
            return new class($name, $basePath) extends Bundle implements ThemeInterface {
                public function __construct(
                    string $name,
                    string $basePath
                ) {
                    $this->name = $name;
                    $this->path = $basePath;
                }
            };
        }

        return new class($name, $basePath) extends Bundle {
            public function __construct(
                string $name,
                string $basePath
            ) {
                $this->name = $name;
                $this->path = $basePath;
            }
        };
    }

    /**
     * @param array<string, array<string, string>> $expected
     */
    private function assertFileCollection(array $expected, FileCollection $files): void
    {
        $flatFiles = [];
        foreach ($files as $file) {
            $flatFiles[$file->getFilepath()] = $file->getResolveMapping();
        }

        static::assertSame($expected, $flatFiles);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\FrontendPluginConfiguration;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Bundle;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Framework\ThemeInterface;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;

/**
 * @internal
 */
class FrontendPluginConfigurationFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractFrontendPluginConfigurationFactory $configFactory;

    protected function setUp(): void
    {
        $this->configFactory = static::getContainer()->get(FrontendPluginConfigurationFactory::class);
    }

    public function testCreateThemeConfig(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/ThemeConfig');
        static::assertIsString($basePath);

        $theme = $this->getBundle('TestTheme', $basePath, true);
        $config = $this->configFactory->createFromBundle($theme);

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
            '@CtTheme',
        ], $config->getViewInheritance());
        static::assertSame(['app/frontend/dist/assets'], $config->getAssetPaths());
        static::assertSame('app/frontend/dist/assets/preview.jpg', $config->getPreviewMedia());
        static::assertSame([
            'fields' => [
                'ct-image' => [
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
        $basePath = realpath(__DIR__ . '/../fixtures/SimplePlugin');
        static::assertIsString($basePath);
        $bundle = $this->getBundle('SimplePlugin', $basePath);

        $config = $this->configFactory->createFromBundle($bundle);

        $this->assertFileCollection(['app/frontend/src/scss/base.scss' => []], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPoint(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimplePluginWithoutCompilation');
        static::assertIsString($basePath);

        $bundle = $this->getBundle('SimplePluginWithoutCompilation', $basePath);
        $config = $this->configFactory->createFromBundle($bundle);

        $this->assertFileCollection([], $config->getStyleFiles());
    }

    public function testPluginHasNoScssEntryPointButDifferentScssFiles(): void
    {
        $basePath = realpath(__DIR__ . '/../fixtures/SimpleWithoutStyleEntryPoint');
        static::assertIsString($basePath);

        $bundle = $this->getBundle('SimpleWithoutStyleEntryPoint', $basePath);

        $config = $this->configFactory->createFromBundle($bundle);

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

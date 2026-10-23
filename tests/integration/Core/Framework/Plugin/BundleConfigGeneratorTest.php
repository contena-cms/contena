<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Plugin;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\Plugin\BundleConfigGenerator;
use Contena\Core\Framework\Plugin\BundleConfigGeneratorInterface;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class BundleConfigGeneratorTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private BundleConfigGeneratorInterface $configGenerator;

    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/';
        $this->configGenerator = static::getContainer()->get(BundleConfigGenerator::class);
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(SourceResolver::class)->reset();
    }

    public function testGenerateAppConfigWithThemeAndScriptAndStylePaths(): void
    {
        $appPath = $this->fixturePath . 'apps/theme/SwagApp/';
        $this->loadAppsFromDir($appPath);
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');

        if (mb_strpos($appPath, $projectDir) === 0) {
            // make relative
            $appPath = ltrim(mb_substr($appPath, mb_strlen($projectDir)), '/');
        }

        $configs = $this->configGenerator->getConfig();

        static::assertArrayHasKey('SwagApp', $configs);

        $appConfig = $configs['SwagApp'];
        static::assertSame(
            $appPath,
            $appConfig['basePath']
        );
        static::assertSame(['Resources/views'], $appConfig['views']);
        static::assertSame('swag-app', $appConfig['technicalName']);
        static::assertArrayNotHasKey('administration', $appConfig);

        static::assertArrayHasKey('frontend', $appConfig);
        $frontendConfig = $appConfig['frontend'];

        static::assertSame('Resources/app/frontend/src', $frontendConfig['path']);
        static::assertSame('Resources/app/frontend/src/main.js', $frontendConfig['entryFilePath']);
        static::assertNull($frontendConfig['webpack']);
        static::assertFalse($frontendConfig['hasComponentAssets']);

        // Style files can and need only be imported if frontend is installed
        if (static::getContainer()->has(FrontendPluginRegistry::class)) {
            $appPath = 'tests/integration/Core/Framework/Plugin/_fixtures/apps/theme/SwagApp/';
            $expectedStyles = [
                $appPath . 'Resources/app/frontend/src/scss/base.scss',
                $appPath . 'Resources/app/frontend/src/scss/overrides.scss',
            ];
            static::assertSame([], array_diff($expectedStyles, $frontendConfig['styleFiles']));
        }
    }

    public function testGenerateAppConfigWithPluginAndScriptAndStylePaths(): void
    {
        $appPath = $this->fixturePath . 'apps/plugin/SwagApp/';
        $this->loadAppsFromDir($appPath);

        $configs = $this->configGenerator->getConfig();
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');

        static::assertArrayHasKey('SwagApp', $configs);

        $appConfig = $configs['SwagApp'];
        static::assertSame(
            realpath($appPath),
            realpath($projectDir . '/' . $appConfig['basePath'])
        );
        static::assertSame(['Resources/views'], $appConfig['views']);
        static::assertSame('swag-app', $appConfig['technicalName']);
        static::assertArrayNotHasKey('administration', $appConfig);

        static::assertArrayHasKey('frontend', $appConfig);
        $frontendConfig = $appConfig['frontend'];

        static::assertSame('Resources/app/frontend/src', $frontendConfig['path']);
        static::assertSame('Resources/app/frontend/src/main.js', $frontendConfig['entryFilePath']);
        static::assertNull($frontendConfig['webpack']);
        static::assertFalse($frontendConfig['hasComponentAssets']);

        // Style files can and need only be imported if frontend is installed
        if (static::getContainer()->has(FrontendPluginRegistry::class)) {
            if (mb_strpos($appPath, $projectDir) === 0) {
                // make relative
                $appPath = ltrim(mb_substr((string) realpath($appPath), mb_strlen($projectDir)), '/');
            }

            // Only base.scss from /_fixture/apps/plugin/SwagApp/ should be included
            $expectedStyles = [
                $appPath . '/Resources/app/frontend/src/scss/base.scss',
            ];

            static::assertSame($expectedStyles, $frontendConfig['styleFiles']);
        }
    }

    public function testGenerateAppConfigIgnoresInactiveApps(): void
    {
        $appPath = $this->fixturePath . 'apps/theme/SwagApp/';
        $this->loadAppsFromDir($appPath, false);

        $configs = $this->configGenerator->getConfig();

        static::assertArrayNotHasKey('SwagApp', $configs);
    }

    public function testGenerateAppConfigWithWebpackConfig(): void
    {
        $appPath = $this->fixturePath . 'apps/SwagTest/';
        $this->loadAppsFromDir($appPath);

        $configs = $this->configGenerator->getConfig();

        static::assertArrayHasKey('SwagTest', $configs);

        $appConfig = $configs['SwagTest'];
        static::assertSame(
            $appPath,
            static::getContainer()->getParameter('kernel.project_dir') . '/' . $appConfig['basePath']
        );
        static::assertSame(['Resources/views'], $appConfig['views']);
        static::assertSame('swag-test', $appConfig['technicalName']);
        static::assertArrayNotHasKey('administration', $appConfig);

        static::assertArrayHasKey('frontend', $appConfig);
        $frontendConfig = $appConfig['frontend'];

        static::assertSame('Resources/app/frontend/src', $frontendConfig['path']);
        static::assertNull($frontendConfig['entryFilePath']);
        static::assertSame('Resources/app/frontend/build/webpack.config.js', $frontendConfig['webpack']);
        static::assertSame([], $frontendConfig['styleFiles']);
        static::assertFalse($frontendConfig['hasComponentAssets']);
    }

    public function testGenerateAppConfigDetectsFrontendComponentAssets(): void
    {
        $this->loadAppsFromDir($this->fixturePath . 'apps/SwagComponentAssets/');

        $configs = $this->configGenerator->getConfig();

        static::assertArrayHasKey('SwagComponentAssets', $configs);

        $appConfig = $configs['SwagComponentAssets'];
        static::assertArrayHasKey('frontend', $appConfig);
        static::assertTrue($appConfig['frontend']['hasComponentAssets']);
    }

    public function testGenerateAppConfigIgnoresNonBuildableFrontendComponentAssets(): void
    {
        $this->loadAppsFromDir($this->fixturePath . 'apps/SwagComponentAssetsIgnored/');

        $configs = $this->configGenerator->getConfig();

        static::assertArrayHasKey('SwagComponentAssetsIgnored', $configs);

        $appConfig = $configs['SwagComponentAssetsIgnored'];
        static::assertArrayHasKey('frontend', $appConfig);
        static::assertFalse($appConfig['frontend']['hasComponentAssets']);
    }
}

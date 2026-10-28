<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\Exception\ThemeException;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Contena\Storefront\Theme\StorefrontPluginRegistry;
use Contena\Storefront\Theme\ThemeFileResolver;
use Contena\Storefront\Theme\ThemeMergedConfigBuilder;
use Contena\Storefront\Theme\ThemeRuntimeConfig;
use Contena\Storefront\Theme\ThemeRuntimeConfigService;
use Contena\Storefront\Theme\ThemeRuntimeConfigStorage;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeRuntimeConfigService::class)]
class ThemeRuntimeConfigServiceTest extends TestCase
{
    private ThemeFileResolver&Stub $themeFileResolver;

    private StorefrontPluginRegistry&Stub $pluginRegistry;

    private ThemeMergedConfigBuilder&Stub $mergedConfigBuilder;

    private ThemeRuntimeConfigStorage&Stub $storage;


    protected function setUp(): void
    {
        $this->themeFileResolver = static::createStub(ThemeFileResolver::class);
        $this->pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $this->mergedConfigBuilder = static::createStub(ThemeMergedConfigBuilder::class);
        $this->storage = static::createStub(ThemeRuntimeConfigStorage::class);

        $this->service = new ThemeRuntimeConfigService(
            $this->themeFileResolver,
            $this->pluginRegistry,
            $this->mergedConfigBuilder,
            $this->storage,
            new NativeClock()
        );
    }



    /**
     * @return iterable<string, array{themeId: string, technicalName: string, expectedConfig: ?ThemeRuntimeConfig}>
     */
    public static function configProvider(): iterable
    {
        yield 'no record found' => [
            'themeId' => '1234567890abcdef1234567890abcde1',
            'technicalName' => 'nonexistent-theme-name',
            'expectedConfig' => null,
        ];

        yield 'config found' => [
            'themeId' => '1234567890abcdef1234567890abcdef',
            'technicalName' => 'test-theme',
            'expectedConfig' => self::createThemeRuntimeConfig(),
        ];
    }

    public function testGetResolvedRuntimeConfigReturnsNull(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn(null);

        $result = $this->createService(storage: $storage)->getResolvedRuntimeConfig($themeId);

        static::assertNull($result);
    }

    public function testGetResolvedRuntimeConfigReturnsConfig(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $config = $this->createThemeRuntimeConfig();

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getById')
            ->with($themeId)
            ->willReturn($config);

        $result = $this->createService(storage: $storage)->getResolvedRuntimeConfig($themeId);

        static::assertSame($config, $result);
    }

    public function testGetResolvedRuntimeConfigResolvesJs(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';

        $partialConfig = $this->createThemeRuntimeConfig(
            themeId: $themeId,
            technicalName: $technicalName,
            scriptFiles: null
        );

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        // Called twice: once in getRuntimeConfig(), once in refreshRuntimeConfig() to preserve importMap.
        $storage
            ->expects($this->exactly(2))
            ->method('getById')
            ->with($themeId)
            ->willReturn($partialConfig);

        $pluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $pluginRegistry
            ->expects($this->once())
            ->method('getConfigurations')
            ->willReturn(
                new StorefrontPluginConfigurationCollection([
                ])
            );

        $storage
            ->expects($this->once())
            ->method('getThemeTechnicalName')
            ->with($themeId)
            ->willReturn($technicalName);

        $storage
            ->expects($this->once())
            ->method('getOwnThemeTechnicalName')
            ->with($themeId)
            ->willReturn($technicalName);

        $scriptFilesCollection = new FileCollection([
        ]);

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects($this->once())
            ->method('resolveScriptFiles')
            ->willReturn($scriptFilesCollection);

        // check that we save new config with resolved js files
        $storage
            ->expects($this->once())
            ->method('save')
            ->with(static::callback(static function (ThemeRuntimeConfig $config) {
                return $config->scriptFiles === ['js/foo/file1.js', 'js/foo/file2.js'];
            }));

        $result = $this->createService(
            themeFileResolver: $themeFileResolver,
            pluginRegistry: $pluginRegistry,
            storage: $storage,
        )->getResolvedRuntimeConfig($themeId);

        // check that updated config is returned
        static::assertNotNull($result);
        static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $result->scriptFiles);
    }

    /**
     * A theme copy must be persisted with a NULL technical name, not its parent's, so copies do not
     * collide on the unique technical_name index.
     */
    public function testGenerateRuntimeConfigForCopyStoresNullTechnicalName(): void
    {
        $copyId = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $parentTechnicalName = 'zenitPlatformGravity';

        $themeConfig = new StorefrontPluginConfiguration($parentTechnicalName);
        $themeConfig->setIconSets(['set1' => 'path/to/icons']);

        $pluginRegistry = static::createStub(StorefrontPluginRegistry::class);
        $pluginRegistry
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$themeConfig]));

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage->expects($this->exactly(2))->method('getById')->with($copyId)->willReturn(null);
        // getThemeTechnicalName() inherits the parent's name; getOwnThemeTechnicalName() is NULL for the copy.
        $storage->expects($this->once())->method('getThemeTechnicalName')->with($copyId)->willReturn($parentTechnicalName);
        $storage->expects($this->once())->method('getOwnThemeTechnicalName')->with($copyId)->willReturn(null);
        $storage->expects($this->once())->method('getCopiesIds')->with($copyId)->willReturn([]);

        $this->mergedConfigBuilder->method('getPlainThemeConfiguration')->willReturn(['key' => 'value']);
        $this->themeFileResolver->method('resolveScriptFiles')->willReturn(new FileCollection());

        $saved = null;
        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (ThemeRuntimeConfig $config) use (&$saved): void {
                $saved = $config;
            });

        $result = $this->createService(
            pluginRegistry: $pluginRegistry,
            storage: $storage,
        )->getRuntimeConfig($copyId);

        static::assertNotNull($saved);
        static::assertNull($saved->technicalName, 'Theme copy must be stored with a NULL technical name');
        static::assertSame($copyId, $saved->themeId);
        static::assertSame(
            ['set1' => ['path' => 'path/to/icons', 'namespace' => $parentTechnicalName]],
            $saved->iconSets
        );

        static::assertNotNull($result);
        static::assertNull($result->technicalName);
    }

    public function testRefreshRuntimeConfig(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();
        $filesRequired = true;

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $themeConfig->setViewInheritance(['parent-theme']);
        $themeConfig->setIconSets(['iconSet1' => 'path/to/iconSet1']);

        $configCollection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        $mergedConfigBuilder = $this->createMock(ThemeMergedConfigBuilder::class);
        $mergedConfigBuilder
            ->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $context)
            ->willReturn(['key' => 'value']);

        $scriptFilesCollection = new FileCollection([
        ]);

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects($this->once())
            ->method('resolveScriptFiles')
            ->with($themeConfig, $configCollection, false)
            ->willReturn($scriptFilesCollection);

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        // No existing config stored — getById called to check for preserved importMap.
        $storage->method('getById')->willReturn(null);
        $storage->expects($this->once())->method('getOwnThemeTechnicalName')->with($themeId)->willReturn($technicalName);

        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function ($config) use ($technicalName): void {
                static::assertInstanceOf(ThemeRuntimeConfig::class, $config);
                static::assertSame($technicalName, $config->technicalName);
                static::assertNotNull($config->scriptFiles);
                static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $config->scriptFiles);
                static::assertNull($config->importMap);
            });

        $result = $this->createService(
            themeFileResolver: $themeFileResolver,
            mergedConfigBuilder: $mergedConfigBuilder,
            storage: $storage,
        )->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);

        static::assertSame($themeId, $result->themeId);
        static::assertSame($technicalName, $result->technicalName);
        static::assertSame(['js/foo/file1.js', 'js/foo/file2.js'], $result->scriptFiles);
        static::assertSame(['key' => 'value'], $result->resolvedConfig);
        static::assertSame(['parent-theme'], $result->viewInheritance);
        static::assertSame(['iconSet1' => ['path' => 'path/to/iconSet1', 'namespace' => $technicalName]], $result->iconSets);
        static::assertNull($result->importMap);
    }

    public function testRefreshRuntimeConfigStoresComponentImportMapWhenProvided(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $configCollection = new StorefrontPluginConfigurationCollection([$themeConfig]);

        $importMap = [
            'imports' => [
                'contena' => '/bundles/storefront/storefront/contena-cms/contena.js',
                'Sw:Button' => 'js/components/Sw/Button.js',
            ],
        ];

        $this->mergedConfigBuilder->method('getPlainThemeConfiguration')->willReturn([]);

        $this->themeFileResolver->method('resolveScriptFiles')
            ->willReturn(new FileCollection());

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function (ThemeRuntimeConfig $config) use ($importMap): void {
                static::assertSame($importMap, $config->importMap);
            });

        $result = $this->createService(storage: $storage)->refreshRuntimeConfig(
            $themeId,
            $themeConfig,
            $context,
            false,
            $configCollection,
            $importMap,
        );

        static::assertSame($importMap, $result->importMap);
    }

    public function testRefreshRuntimeConfigPreservesComponentImportMapFromStorageOnNonCompileRefresh(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $configCollection = new StorefrontPluginConfigurationCollection([$themeConfig]);

        $existingImportMap = [
            'imports' => ['Sw:Button' => 'js/components/Sw/Button.js'],
        ];

        $existingConfig = $this->createThemeRuntimeConfig(
            themeId: $themeId,
            technicalName: $technicalName,
        );
        // Set importMap via `with()` since createThemeRuntimeConfig doesn't expose it as param.
        $existingConfig = $existingConfig->with(['importMap' => $existingImportMap]);

        $this->mergedConfigBuilder->method('getPlainThemeConfiguration')->willReturn([]);
        $this->themeFileResolver->method('resolveScriptFiles')->willReturn(new FileCollection());

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        // Storage is read to retrieve the existing importMap; no explicit import map passed.
        $storage->method('getById')->willReturn($existingConfig);

        $storage->expects($this->once())->method('save')
            ->willReturnCallback(static function (ThemeRuntimeConfig $config) use ($existingImportMap): void {
                static::assertSame($existingImportMap, $config->importMap);
            });

        // Pass null for importMap (non-compile refresh).
        $result = $this->createService(storage: $storage)->refreshRuntimeConfig(
            $themeId,
            $themeConfig,
            $context,
            false,
            $configCollection,
            null,
        );

        static::assertSame($existingImportMap, $result->importMap);
    }

    public function testRefreshRuntimeConfigIgnoresJsExceptionWhenFilesNotRequired(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();
        $filesRequired = false;

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $themeConfig->setViewInheritance(['parent-theme']);
        $themeConfig->setIconSets(['iconSet1' => 'path/to/iconSet1']);

        $configCollection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        $mergedConfigBuilder = $this->createMock(ThemeMergedConfigBuilder::class);
        $mergedConfigBuilder
            ->expects($this->once())
            ->method('getPlainThemeConfiguration')
            ->with($themeId, $context)
            ->willReturn(['key' => 'value']);

        $themeFileResolver = $this->createMock(ThemeFileResolver::class);
        $themeFileResolver
            ->expects($this->once())
            ->method('resolveScriptFiles')
            ->willThrowException(ThemeException::themeCompileException($technicalName, 'Failed to resolve js files'));

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage->method('getById')->willReturn(null);

        $storage
            ->expects($this->once())
            ->method('save')
            ->willReturnCallback(static function ($config): void {
                static::assertInstanceOf(ThemeRuntimeConfig::class, $config);
                static::assertNull($config->scriptFiles);
                static::assertNull($config->importMap);
            });

        $result = $this->createService(
            themeFileResolver: $themeFileResolver,
            mergedConfigBuilder: $mergedConfigBuilder,
            storage: $storage,
        )->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);

        static::assertSame($themeId, $result->themeId);
        static::assertNull($result->scriptFiles);
        static::assertNull($result->importMap);
    }

    public function testRefreshRuntimeConfigPropagatesJsExceptionWhenFilesRequired(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $context = Context::createDefaultContext();
        $filesRequired = true;

        $themeConfig = new StorefrontPluginConfiguration($technicalName);
        $themeConfig->setViewInheritance(['parent-theme']);
        $themeConfig->setIconSets(['iconSet1' => 'path/to/iconSet1']);

        $configCollection = new StorefrontPluginConfigurationCollection([
            $themeConfig,
        ]);

        // Make resolveJs throw an exception
        $exception = ThemeException::themeCompileException($technicalName, 'Failed to resolve js files');
        $this->themeFileResolver
            ->method('resolveScriptFiles')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->service->refreshRuntimeConfig($themeId, $themeConfig, $context, $filesRequired, $configCollection);
    }

    public function testResetCaches(): void
    {
        $themeId = '1234567890abcdef1234567890abcdef';
        $technicalName = 'test-theme';
        $activeThemeNames = ['theme1', 'theme2'];

        $config = $this->createThemeRuntimeConfig($themeId, $technicalName);

        // storage should be called 2 times, before and after reset
        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->exactly(2))
            ->method('getById')
            ->with($themeId)
            ->willReturn($config);

        $storage
            ->expects($this->exactly(2))
            ->method('getActiveThemeNames')
            ->willReturn($activeThemeNames);

        $service = $this->createService(storage: $storage);

        // Populate caches
        $service->getRuntimeConfig($themeId);
        $service->getRuntimeConfigByName($technicalName);
        $service->getActiveThemeNames();

        // Reset all caches
        $service->resetCaches();

        // Load from storage
        $service->getRuntimeConfig($themeId);
        $service->getRuntimeConfigByName($technicalName);
        $service->getActiveThemeNames();
        $service->getActiveThemeNames();
    }

    public function testGetActiveThemeNames(): void
    {
        $expectedNames = ['theme1', 'theme2'];

        // Only one storage access for two calls
        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('getActiveThemeNames')
            ->willReturn($expectedNames);

        $service = $this->createService(storage: $storage);

        // First call - should hit storage, second - use cache
        $result1 = $service->getActiveThemeNames();
        $result2 = $service->getActiveThemeNames();

        static::assertSame($expectedNames, $result1);
        static::assertSame($expectedNames, $result2);
    }

    public function testDeleteByTechnicalName(): void
    {
        $technicalName = 'test-theme';

        $storage = $this->createMock(ThemeRuntimeConfigStorage::class);
        $storage
            ->expects($this->once())
            ->method('deleteByTechnicalName')
            ->with($technicalName);

        // Verify cache is cleared by checking storage is called again after delete
        $storage
            ->expects($this->exactly(2))
            ->method('getByName')
            ->with($technicalName)
            ->willReturn(null);

        $service = $this->createService(storage: $storage);

        // Populate cache
        $service->getRuntimeConfigByName($technicalName);

        // Delete - should call storage and reset cache
        $service->deleteByTechnicalName($technicalName);

        // This call should hit storage again (cache was reset)
        $service->getRuntimeConfigByName($technicalName);
    }


}

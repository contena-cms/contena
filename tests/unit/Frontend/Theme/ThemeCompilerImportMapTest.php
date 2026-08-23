<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Frontend\Theme\AbstractScssCompiler;
use Contena\Frontend\Theme\AbstractThemePathBuilder;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerImportMapTest extends TestCase
{
    private Filesystem $assetFilesystem;

    private ThemeCompiler $compiler;

    protected function setUp(): void
    {
        $this->assetFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->compiler = $this->createCompilerForBundleBuildMeta();
    }

    public function testBuildComponentImportMapReadsFromBundleAssetsDirectory(): void
    {
        $this->writeJson(
            'bundles/frontend/frontend/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'Example/Component.ts' => ['file' => 'Example/Component-HASH.js', 'name' => 'Example/Component', 'isEntry' => true],
                    'Example/Component.scss' => ['file' => 'Example/Component-HASH.css', 'name' => 'Example/Component.scss', 'isEntry' => true],
                ],
                'vendorMap' => [],
            ]
        );

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap());

        static::assertSame(
            '/bundles/frontend/frontend/components/Example/Component-HASH.js',
            $result['imports']['Example:Component']
        );
        static::assertSame(
            ['/bundles/frontend/frontend/components/Example/Component-HASH.css'],
            $result['styles'] ?? []
        );
    }

    public function testBuildComponentImportMapUsesBundleVendorMapForScopes(): void
    {
        $this->writeJson(
            'bundles/myextension/frontend/components/.vite/build-meta.json',
            ['manifest' => [], 'vendorMap' => ['@vendor/chunk' => 'vendor/chunk-HASH.js']]
        );
        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('MyExtension'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertSame(
            [
                '/bundles/myextension/frontend/components/MyExtension/' => [
                    '@vendor/chunk' => '/bundles/myextension/frontend/components/vendor/chunk-HASH.js',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapSkipsBundleWhenBuildMetaJsonIsInvalid(): void
    {
        $path = 'bundles/brokenextension/frontend/components/.vite/build-meta.json';
        $this->assetFilesystem->createDirectory('bundles/brokenextension/frontend/components/.vite');
        $this->assetFilesystem->write($path, '{invalid json');
        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('BrokenExtension'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertArrayNotHasKey('BrokenExtension:Card', $result['imports']);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testBuildComponentImportMapFallsBackToEmptyArraysForInvalidBuildMetaStructure(): void
    {
        $this->writeJson(
            'bundles/invalidmeta/frontend/components/.vite/build-meta.json',
            ['manifest' => 'invalid', 'vendorMap' => 'invalid']
        );
        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('InvalidMeta'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertArrayNotHasKey('InvalidMeta:Component', $result['imports']);
        static::assertArrayNotHasKey('scopes', $result);
    }

    public function testBuildComponentImportMapRefreshesBuildMetaBetweenCalls(): void
    {
        $metaPath = 'bundles/cachedextension/frontend/components/.vite/build-meta.json';
        $this->writeJson(
            $metaPath,
            ['manifest' => [], 'vendorMap' => ['@cached/chunk' => 'vendor/chunk-one.js']]
        );
        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('CachedExtension'),
        ]);

        $firstResult = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        $this->writeJson(
            $metaPath,
            ['manifest' => [], 'vendorMap' => ['@cached/chunk' => 'vendor/chunk-two.js']]
        );
        $secondResult = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));
        static::assertArrayHasKey('scopes', $firstResult);
        static::assertArrayHasKey('scopes', $secondResult);
        static::assertSame(
            '/bundles/cachedextension/frontend/components/vendor/chunk-one.js',
            $firstResult['scopes']['/bundles/cachedextension/frontend/components/CachedExtension/']['@cached/chunk']
        );
        static::assertSame(
            '/bundles/cachedextension/frontend/components/vendor/chunk-two.js',
            $secondResult['scopes']['/bundles/cachedextension/frontend/components/CachedExtension/']['@cached/chunk']
        );
    }

    public function testBuildComponentImportMapCollectsEntriesOnlyFromProvidedBundles(): void
    {
        $this->writeJson(
            'bundles/frontend/frontend/components/.vite/build-meta.json',
            [
                'manifest' => ['Core/Button.ts' => ['file' => 'Core/Button-HASH.js', 'name' => 'Core/Button', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );
        $this->writeJson(
            'bundles/myextension/frontend/components/.vite/build-meta.json',
            [
                'manifest' => ['MyExtension/Card.ts' => ['file' => 'MyExtension/Card-HASH.js', 'name' => 'MyExtension/Card', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );

        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('MyExtension'),
        ]);
        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertSame(
            '/bundles/frontend/frontend/components/Core/Button-HASH.js',
            $result['imports']['Core:Button']
        );
        static::assertSame(
            '/bundles/myextension/frontend/components/MyExtension/Card-HASH.js',
            $result['imports']['MyExtension:Card']
        );
    }

    public function testBuildComponentImportMapIgnoresBundlesOutsideConfigurationCollection(): void
    {
        $this->writeJson(
            'bundles/frontend/frontend/components/.vite/build-meta.json',
            [
                'manifest' => ['Core/Button.ts' => ['file' => 'Core/Button-HASH.js', 'name' => 'Core/Button', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );
        $this->writeJson(
            'bundles/inactiveextension/frontend/components/.vite/build-meta.json',
            [
                'manifest' => ['InactiveExtension/Card.ts' => ['file' => 'InactiveExtension/Card-HASH.js', 'name' => 'InactiveExtension/Card', 'isEntry' => true]],
                'vendorMap' => [],
            ]
        );

        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
        ]);

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertIsArray($result);
        static::assertArrayHasKey('imports', $result);
        static::assertSame('/bundles/frontend/frontend/contena/contena.js', $result['imports']['contena']);
        static::assertArrayHasKey('Core:Button', $result['imports']);
        static::assertArrayNotHasKey('InactiveExtension:Card', $result['imports']);
    }

    public function testBuildComponentImportMapDeduplicatesCssAndSkipsInvalidManifestEntries(): void
    {
        $this->writeJson(
            'bundles/myextension/frontend/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyExtension/Card.ts' => [
                        'file' => 'MyExtension/Card-HASH.js',
                        'name' => 'MyExtension/Card',
                        'isEntry' => true,
                        'css' => ['MyExtension/Card-HASH.css', 'MyExtension/Card-HASH.css'],
                    ],
                    'MyExtension/Card.scss' => [
                        'file' => 'MyExtension/Card-HASH.css',
                        'name' => 'MyExtension/Card.scss',
                        'isEntry' => true,
                    ],
                    'MyExtension/NoName.ts' => [
                        'file' => 'MyExtension/NoName-HASH.js',
                        'isEntry' => true,
                    ],
                    'MyExtension/NotEntry.ts' => [
                        'file' => 'MyExtension/NotEntry-HASH.js',
                        'name' => 'MyExtension/NotEntry',
                        'isEntry' => false,
                    ],
                ],
                'vendorMap' => [],
            ]
        );

        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('MyExtension'),
        ]);
        $result = $this->compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            '/bundles/myextension/frontend/components/MyExtension/Card-HASH.js',
            $result['imports']['MyExtension:Card']
        );
        static::assertSame(
            ['/bundles/myextension/frontend/components/MyExtension/Card-HASH.css'],
            $result['styles'] ?? []
        );
    }

    public function testBuildComponentImportMapCreatesRelativeScopesAndStyles(): void
    {
        $this->writeJson(
            'bundles/frontend/frontend/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'Core/Button.ts' => [
                        'file' => 'Core/Button-HASH.js',
                        'name' => 'Core/Button',
                        'isEntry' => true,
                        'css' => ['Core/Button-HASH.css'],
                    ],
                    'Core/Button.scss' => [
                        'file' => 'Core/Button-HASH.css',
                        'name' => 'Core/Button.scss',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => ['@core/vendor' => 'vendor/core-HASH.js'],
            ]
        );
        $this->writeJson(
            'bundles/myextension/frontend/components/.vite/build-meta.json',
            [
                'manifest' => [
                    'MyExtension/Card.ts' => [
                        'file' => 'MyExtension/Card-HASH.js',
                        'name' => 'MyExtension/Card',
                        'isEntry' => true,
                    ],
                ],
                'vendorMap' => ['@ext/vendor' => 'vendor/ext-HASH.js'],
            ]
        );

        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('MyExtension'),
        ]);

        $result = $this->compiler->buildComponentImportMap($collection);

        static::assertIsArray($result);
        static::assertSame(
            '/bundles/frontend/frontend/contena/contena.js',
            $result['imports']['contena']
        );
        static::assertSame(
            '/bundles/frontend/frontend/components/vendor/core-HASH.js',
            $result['imports']['@core/vendor']
        );
        static::assertSame(
            ['/bundles/frontend/frontend/components/Core/Button-HASH.css'],
            $result['styles'] ?? []
        );
        static::assertSame(
            [
                '/bundles/myextension/frontend/components/MyExtension/' => [
                    '@ext/vendor' => '/bundles/myextension/frontend/components/vendor/ext-HASH.js',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapWithNullConfigurationCollectionReturnsOnlyImports(): void
    {
        $this->writeJson(
            'bundles/frontend/frontend/components/.vite/build-meta.json',
            [
                'manifest' => [],
                'vendorMap' => [],
            ]
        );

        $result = $this->compiler->buildComponentImportMap();

        static::assertSame(
            [
                'imports' => [
                    'contena' => '/bundles/frontend/frontend/contena/contena.js',
                ],
            ],
            $result
        );
    }

    public function testBuildComponentImportMapFallsBackToRelativeContenaPathWithoutMatchingPackage(): void
    {
        $compiler = $this->createCompilerForBundleBuildMeta([
            'theme' => new UrlPackage('https://cdn.example.com/theme', new EmptyVersionStrategy()),
        ]);

        static::assertSame(
            [
                'imports' => [
                    'contena' => '/bundles/frontend/frontend/contena/contena.js',
                ],
            ],
            $compiler->buildComponentImportMap()
        );
    }

    public function testBuildComponentImportMapIgnoresEmptyBuildMetaContent(): void
    {
        $path = 'bundles/frontend/frontend/components/.vite/build-meta.json';
        $this->assetFilesystem->createDirectory('bundles/frontend/frontend/components/.vite');
        $this->assetFilesystem->write($path, '');

        static::assertSame(
            [
                'imports' => [
                    'contena' => '/bundles/frontend/frontend/contena/contena.js',
                ],
            ],
            $this->compiler->buildComponentImportMap()
        );
    }

    public function testBuildComponentImportMapNormalizesScalarBuildMetaJsonToEmptyArrays(): void
    {
        $path = 'bundles/frontend/frontend/components/.vite/build-meta.json';
        $this->assetFilesystem->createDirectory('bundles/frontend/frontend/components/.vite');
        $this->assetFilesystem->write($path, '1');

        static::assertSame(
            [
                'imports' => [
                    'contena' => '/bundles/frontend/frontend/contena/contena.js',
                ],
            ],
            $this->compiler->buildComponentImportMap()
        );
    }

    public function testBuildComponentImportMapSkipsComponentImportsWhenManifestIsEmpty(): void
    {
        $this->writeJson(
            'bundles/emptyextension/frontend/components/.vite/build-meta.json',
            [
                'manifest' => [],
                'vendorMap' => ['@vendor/chunk' => 'vendor/chunk-HASH.js'],
            ]
        );

        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
            new FrontendPluginConfiguration('EmptyExtension'),
        ]);
        $result = $this->assertImportMap($this->compiler->buildComponentImportMap($collection));

        static::assertArrayNotHasKey('EmptyExtension:Card', $result['imports']);
        static::assertArrayHasKey('scopes', $result);
        static::assertSame(
            [
                '/bundles/emptyextension/frontend/components/EmptyExtension/' => [
                    '@vendor/chunk' => '/bundles/emptyextension/frontend/components/vendor/chunk-HASH.js',
                ],
            ],
            $result['scopes'] ?? []
        );
    }

    public function testBuildComponentImportMapIsIndependentFromAssetPackageConfiguration(): void
    {
        $compiler = $this->createCompilerForBundleBuildMeta([
            'public' => new UrlPackage('https://cdn.example.com/public', new EmptyVersionStrategy()),
            'asset' => new UrlPackage('https://cdn.example.com/asset', new EmptyVersionStrategy()),
        ]);
        $collection = new FrontendPluginConfigurationCollection([
            new FrontendPluginConfiguration('Frontend'),
        ]);

        $result = $this->assertImportMap($compiler->buildComponentImportMap($collection));
        static::assertSame(
            '/bundles/frontend/frontend/contena/contena.js',
            $result['imports']['contena']
        );
    }

    public function testBuildComponentImportMapReadsUnversionedFilesystemPathAndEmitsRelativeBundlePaths(): void
    {
        $versionedMeta = [
            'manifest' => [
                '../../views/components/Contena/Custom/Test.js' => [
                    'file' => 'Contena/Custom/Test-HASH.js',
                    'name' => 'Contena/Custom/Test',
                    'isEntry' => true,
                ],
            ],
            'vendorMap' => [],
        ];

        $this->writeJson(
            'bundles/frontend/frontend/components/.vite/build-meta.json',
            $versionedMeta
        );

        $result = $this->assertImportMap($this->compiler->buildComponentImportMap());
        static::assertSame(
            '/bundles/frontend/frontend/components/Contena/Custom/Test-HASH.js',
            $result['imports']['Contena:Custom:Test']
        );
    }

    /**
     * @param array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>}|null $result
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>}
     */
    private function assertImportMap(?array $result): array
    {
        static::assertIsArray($result);
        static::assertArrayHasKey('imports', $result);

        return $result;
    }

    /**
     * @param array<string, UrlPackage> $packages
     */
    private function createCompilerForBundleBuildMeta(array $packages = []): ThemeCompiler
    {
        $themePathBuilder = static::createStub(AbstractThemePathBuilder::class);
        $themePathBuilder->method('assemblePath')->willReturn('theme-path');
        if ($packages === []) {
            $packages = [
                'asset' => new UrlPackage('https://cdn.example.com', new EmptyVersionStrategy()),
            ];
        }

        return new ThemeCompiler(
            static::createStub(FilesystemOperator::class),
            static::createStub(FilesystemOperator::class),
            $this->assetFilesystem,
            new CopyBatchInputFactory(),
            static::createStub(ThemeFileResolver::class),
            true,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ThemeFilesystemResolver::class),
            $packages,
            static::createStub(CacheInvalidator::class),
            static::createStub(LoggerInterface::class),
            $themePathBuilder,
            static::createStub(AbstractScssCompiler::class),
            [],
            false,
            'public',
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): void
    {
        $directory = \dirname($path);
        if ($directory !== '.' && !$this->assetFilesystem->directoryExists($directory)) {
            $this->assetFilesystem->createDirectory($directory);
        }

        $this->assetFilesystem->write(
            $path,
            json_encode($data, \JSON_THROW_ON_ERROR),
        );
    }
}

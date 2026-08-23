<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentSystemElementTypeCompilerPass;
use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Contena\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeCompilerPass::class)]
class ContentSystemElementTypeCompilerPassTest extends TestCase
{
    private const FIXTURES_DIR = __DIR__ . '/fixtures';

    private ContentSystemElementTypeCompilerPass $pass;

    protected function setUp(): void
    {
        $this->pass = new ContentSystemElementTypeCompilerPass();
    }

    #[TestDox('scans non-plugin bundles using the standard type directory path')]
    public function testScansNonPluginBundlesForTypes(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', [
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $bundleDir = $this->findBySource($this->extractDirectories($container), 'bundle:BundleA');
        static::assertNotNull($bundleDir);
        static::assertSame(self::FIXTURES_DIR . '/bundle-a/Resources/content-system/types', $bundleDir->getArgument(1));
        static::assertSame('Sw', $bundleDir->getArgument(2));
    }

    #[TestDox('loads active plugins from their configured type directory')]
    public function testLoadsPluginTypesFromConfiguredDirectory(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                'name' => 'FixturePlugin',
                'path' => self::FIXTURES_DIR . '/test-plugin',
                'class' => FixturePlugin::class,
            ],
        ]);

        $this->pass->process($container);

        $pluginDir = $this->findBySource($this->extractDirectories($container), 'plugin:FixturePlugin');
        static::assertNotNull($pluginDir);
        static::assertSame(self::FIXTURES_DIR . '/test-plugin/Resources/content-system/types', $pluginDir->getArgument(1));
        static::assertSame('FixturePlugin', $pluginDir->getArgument(2));
    }

    #[TestDox('skips active-plugin bundles during bundle-metadata loading')]
    public function testSkipsActivePluginBundlesDuringBundleScan(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', [
            'MyPlugin' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', [
            FixturePlugin::class => [
                'name' => 'MyPlugin',
                'path' => '/plugins/my-plugin',
                'class' => FixturePlugin::class,
            ],
        ]);

        $this->pass->process($container);

        static::assertNull($this->findBySource($this->extractDirectories($container), 'bundle:MyPlugin'));
    }

    #[TestDox('feeds both loaders the same directory set covering core, bundle, and plugin entries')]
    public function testFeedsBothLoadersTheSameDirectorySet(): void
    {
        $container = $this->buildContainerWithBothLoaders();
        $container->setParameter('kernel.bundles_metadata', [
            'BundleA' => ['path' => self::FIXTURES_DIR . '/bundle-a'],
        ]);
        $container->setParameter('kernel.active_plugins', [
            FixturePluginWithCustomTypeDir::class => [
                'name' => 'FixturePluginWithCustomTypeDir',
                'path' => self::FIXTURES_DIR . '/test-plugin-custom',
                'class' => FixturePluginWithCustomTypeDir::class,
            ],
        ]);

        $this->pass->process($container);

        $typeDirectories = $this->extractDirectories($container);
        $bindingDirectories = $this->extractDirectories($container, YamlBindingSpecificationLoader::class);

        static::assertEquals($typeDirectories, $bindingDirectories);

        $core = $this->findBySource($typeDirectories, 'core');
        static::assertNotNull($core);
        $corePath = $core->getArgument(1);
        static::assertIsString($corePath);
        static::assertStringEndsWith('ContentSystem/Layout/Type/Definitions', $corePath);
        static::assertSame('Sw', $core->getArgument(2));

        $bundle = $this->findBySource($typeDirectories, 'bundle:BundleA');
        static::assertNotNull($bundle);
        static::assertSame(self::FIXTURES_DIR . '/bundle-a/Resources/content-system/types', $bundle->getArgument(1));
        static::assertSame('Sw', $bundle->getArgument(2));

        $plugin = $this->findBySource($typeDirectories, 'plugin:FixturePluginWithCustomTypeDir');
        static::assertNotNull($plugin);
        static::assertSame(self::FIXTURES_DIR . '/test-plugin-custom/custom-types', $plugin->getArgument(1));
        static::assertSame('FixturePluginWithCustomTypeDir', $plugin->getArgument(2));
    }

    #[TestDox('feeds the binding loader even when the type loader service is absent')]
    public function testFeedsBindingLoaderWhenTypeLoaderIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);
        $container->setDefinition(YamlBindingSpecificationLoader::class, new Definition(YamlBindingSpecificationLoader::class));

        $this->pass->process($container);

        $directories = $this->extractDirectories($container, YamlBindingSpecificationLoader::class);
        static::assertNotNull($this->findBySource($directories, 'core'));
    }

    #[TestDox('does nothing when the YAML loader service is absent')]
    public function testRegistersNothingWhenLoaderServiceIsAbsent(): void
    {
        $container = new ContainerBuilder();
        $definitionsBefore = $container->getDefinitions();

        $this->pass->process($container);

        static::assertSame($definitionsBefore, $container->getDefinitions());
    }

    #[TestDox('throws when kernel.bundles_metadata is not an array')]
    public function testThrowsWhenBundlesMetadataIsNotAnArray(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');
        $container->setParameter('kernel.active_plugins', []);

        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        $this->pass->process($container);
    }

    /**
     * @param array<string, mixed> $bundlesMetadata
     */
    #[DataProvider('throwsForInvalidActivePluginsProvider')]
    #[TestDox('throws for invalid active plugins configuration')]
    public function testThrowsForInvalidActivePluginsConfiguration(array $bundlesMetadata, mixed $activePlugins, DependencyInjectionException $expectedException): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', $bundlesMetadata);
        $container->setParameter('kernel.active_plugins', $activePlugins);

        $this->expectExceptionObject($expectedException);

        $this->pass->process($container);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, mixed, DependencyInjectionException}>
     */
    public static function throwsForInvalidActivePluginsProvider(): iterable
    {
        yield 'active_plugins is not an array' => [
            [],
            'not-an-array',
            DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', 'string'),
        ];
        yield 'plugin key is not a valid class' => [
            [],
            ['Missing\\Plugin' => ['name' => 'Plugin', 'path' => '/plugin', 'class' => 'Missing\\Plugin']],
            DependencyInjectionException::parameterHasWrongType(
                'kernel.active_plugins',
                'array<class-string, array>',
                'entry key "Missing\\Plugin" is not a valid class'
            ),
        ];
        yield 'plugin entry missing required metadata fields' => [
            [],
            [FixturePlugin::class => ['name' => 'FixturePlugin']],
            DependencyInjectionException::parameterHasWrongType(
                'kernel.active_plugins',
                'array{name: string, path: string, class: string}',
                \sprintf('entry for "%s" has missing or invalid metadata', FixturePlugin::class)
            ),
        ];
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(YamlTypeLoader::class, new Definition(YamlTypeLoader::class));

        return $container;
    }

    private function buildContainerWithBothLoaders(): ContainerBuilder
    {
        $container = $this->buildContainer();
        $container->setDefinition(YamlBindingSpecificationLoader::class, new Definition(YamlBindingSpecificationLoader::class));

        return $container;
    }

    /**
     * @return list<Definition>
     */
    private function extractDirectories(ContainerBuilder $container, string $loader = YamlTypeLoader::class): array
    {
        return $container->getDefinition($loader)->getArgument('$directories');
    }

    /**
     * @param list<Definition> $directories
     */
    private function findBySource(array $directories, string $source): ?Definition
    {
        foreach ($directories as $directory) {
            if ($directory->getArgument(0) === $source) {
                return $directory;
            }
        }

        return null;
    }
}

/**
 * @internal
 */
class FixturePlugin extends Plugin
{
}

/**
 * @internal
 */
class FixturePluginWithCustomTypeDir extends Plugin
{
    public static function getContentTypeDirectory(): string
    {
        return 'custom-types';
    }
}

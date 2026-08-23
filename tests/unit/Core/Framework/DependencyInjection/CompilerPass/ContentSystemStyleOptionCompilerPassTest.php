<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ContentSystemStyleOptionCompilerPass;
use Contena\Core\Framework\DependencyInjection\DependencyInjectionException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ContentSystemStyleOptionCompilerPass::class)]
class ContentSystemStyleOptionCompilerPassTest extends TestCase
{
    private const STYLE_DIR = '/Resources/content-system/style-options';

    private ContentSystemStyleOptionCompilerPass $pass;

    protected function setUp(): void
    {
        $this->pass = new ContentSystemStyleOptionCompilerPass();
    }

    #[TestDox('injects the core definitions directory')]
    public function testInjectsCoreDirectory(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $coreDir = $this->findBySource($this->extractDirectories($container), 'core');
        static::assertNotNull($coreDir);
        static::assertStringEndsWith('Layout/Element/Style/Definitions', $this->path($coreDir));
    }

    #[TestDox('scans a non-plugin bundle using the fixed convention directory and bundle label')]
    public function testScansBundleWithFixedDirectory(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', ['BundleA' => ['path' => '/bundles/bundle-a']]);
        $container->setParameter('kernel.active_plugins', []);

        $this->pass->process($container);

        $bundleDir = $this->findBySource($this->extractDirectories($container), 'bundle:BundleA');
        static::assertNotNull($bundleDir);
        static::assertSame('/bundles/bundle-a' . self::STYLE_DIR, $this->path($bundleDir));
    }

    #[TestDox('labels a bundle that is an active plugin with the plugin prefix')]
    public function testLabelsActivePluginBundleAsPlugin(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', ['MyPlugin' => ['path' => '/plugins/my-plugin']]);
        $container->setParameter('kernel.active_plugins', [
            'My\\Plugin\\MyPlugin' => ['name' => 'MyPlugin', 'path' => '/plugins/my-plugin', 'class' => 'My\\Plugin\\MyPlugin'],
        ]);

        $this->pass->process($container);

        $directories = $this->extractDirectories($container);
        $pluginDir = $this->findBySource($directories, 'plugin:MyPlugin');
        static::assertNotNull($pluginDir);
        static::assertSame('/plugins/my-plugin' . self::STYLE_DIR, $this->path($pluginDir));
        static::assertNull($this->findBySource($directories, 'bundle:MyPlugin'));
    }

    #[TestDox('does nothing when the YAML loader service is absent')]
    public function testSkipsWhenLoaderAbsent(): void
    {
        $container = new ContainerBuilder();

        $this->expectNotToPerformAssertions();
        $this->pass->process($container);
    }

    #[TestDox('fails hard when bundles metadata is not an array')]
    public function testThrowsWhenBundlesMetadataNotArray(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', 'not-an-array');
        $container->setParameter('kernel.active_plugins', []);

        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        $this->pass->process($container);
    }

    #[TestDox('fails hard when active plugins is not an array')]
    public function testThrowsWhenActivePluginsNotArray(): void
    {
        $container = $this->buildContainer();
        $container->setParameter('kernel.bundles_metadata', []);
        $container->setParameter('kernel.active_plugins', 'not-an-array');

        $this->expectExceptionObject(DependencyInjectionException::parameterHasWrongType('kernel.active_plugins', 'array', 'string'));

        $this->pass->process($container);
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(YamlStyleOptionLoader::class, new Definition(YamlStyleOptionLoader::class));

        return $container;
    }

    /**
     * @return list<Definition>
     */
    private function extractDirectories(ContainerBuilder $container): array
    {
        $directories = $container->getDefinition(YamlStyleOptionLoader::class)->getArgument('$directories');
        static::assertIsArray($directories);

        return array_values($directories);
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

    private function path(Definition $directory): string
    {
        $path = $directory->getArgument(1);
        static::assertIsString($path);

        return $path;
    }
}

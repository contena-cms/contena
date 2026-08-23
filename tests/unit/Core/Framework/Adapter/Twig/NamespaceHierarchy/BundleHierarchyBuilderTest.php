<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Contena\Core\Framework\Bundle;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(BundleHierarchyBuilder::class)]
class BundleHierarchyBuilderTest extends TestCase
{
    /**
     * @param array<string, int> $plugins
     * @param array<int, string> $expectedSorting
     */
    #[DataProvider('sortingProvider')]
    public function testSortingOfTemplates(array $plugins, array $expectedSorting): void
    {
        $kernel = static::createStub(KernelInterface::class);
        $bundles = [];

        $path = __DIR__ . '/../../../../../../integration/Core/Framework/Adapter/Twig/fixtures/Plugins/TestPlugin1/';

        foreach ($plugins as $plugin => $prio) {
            $bundles[] = new MockBundle($plugin, $prio, $path);
        }

        $kernel->method('getBundles')->willReturn($bundles);

        $builder = new BundleHierarchyBuilder($kernel);

        static::assertSame($expectedSorting, array_keys($builder->buildNamespaceHierarchy([])));
    }

    /**
     * @return iterable<string, array<array<int|string, int|string>>>
     */
    public static function sortingProvider(): iterable
    {
        yield 'all with default prio' => [
            ['TestPluginB' => 0, 'TestPluginA' => 0],
            ['TestPluginA', 'TestPluginB'],
        ];

        yield 'one plugin with high prio' => [
            ['TestPluginB' => -500, 'TestPluginA' => 0],
            ['TestPluginB', 'TestPluginA'],
        ];

        yield 'both plugins use their configured priorities' => [
            ['TestPluginB' => -500, 'TestPluginA' => -400],
            ['TestPluginB', 'TestPluginA'],
        ];
    }
}

/**
 * @internal
 */
class MockBundle extends Bundle
{
    public function __construct(
        string $name,
        private readonly int $templatePriority,
        string $path
    ) {
        $this->name = $name;
        $this->path = $path;
    }

    public function getTemplatePriority(): int
    {
        return $this->templatePriority;
    }
}

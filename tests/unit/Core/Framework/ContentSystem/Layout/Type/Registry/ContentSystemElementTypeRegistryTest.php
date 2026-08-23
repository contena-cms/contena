<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\AbstractContentSystemElementTypeLoader;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[CoversClass(ContentSystemElementTypeRegistry::class)]
class ContentSystemElementTypeRegistryTest extends TestCase
{
    #[TestDox('aggregates specifications from multiple loaders')]
    public function testAllAggregatesFromMultipleLoaders(): void
    {
        $specA = $this->createSpec('CT:Content:Text', 'Text', 'core');
        $specB = $this->createSpec('App:Demo:Hero', 'Hero', 'plugin:Demo');

        $loaderA = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loaderA->method('load')->willReturn([$specA]);

        $loaderB = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loaderB->method('load')->willReturn([$specB]);

        $registry = new ContentSystemElementTypeRegistry([$loaderA, $loaderB]);

        $all = $registry->all();
        static::assertCount(2, $all);
        static::assertArrayHasKey('CT:Content:Text', $all);
        static::assertArrayHasKey('App:Demo:Hero', $all);
    }

    #[TestDox('returns empty array when no loaders are registered')]
    public function testAllReturnsEmptyArrayWithNoLoaders(): void
    {
        $registry = new ContentSystemElementTypeRegistry([]);

        static::assertSame([], $registry->all());
    }

    #[TestDox('returns true for a registered type')]
    public function testHasReturnsTrueForRegisteredType(): void
    {
        $loader = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loader->method('load')->willReturn([$this->createSpec('CT:Content:Text', 'Text')]);

        $registry = new ContentSystemElementTypeRegistry([$loader]);

        static::assertTrue($registry->has('CT:Content:Text'));
    }

    #[TestDox('returns false for an unknown type')]
    public function testHasReturnsFalseForUnknownType(): void
    {
        $registry = new ContentSystemElementTypeRegistry([]);

        static::assertFalse($registry->has('CT:Unknown:Type'));
    }

    #[TestDox('returns the specification for a registered type')]
    public function testGetReturnsSpecificationForRegisteredType(): void
    {
        $spec = $this->createSpec('CT:Content:Text', 'Text');

        $loader = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loader->method('load')->willReturn([$spec]);

        $registry = new ContentSystemElementTypeRegistry([$loader]);

        static::assertSame($spec, $registry->get('CT:Content:Text'));
    }

    #[TestDox('throws DecorationPatternException when calling getDecorated')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $registry = new ContentSystemElementTypeRegistry([]);

        $this->expectExceptionObject(new DecorationPatternException(ContentSystemElementTypeRegistry::class));
        $registry->getDecorated();
    }

    #[TestDox('throws for unknown type on get')]
    public function testGetThrowsForUnknownType(): void
    {
        $registry = new ContentSystemElementTypeRegistry([]);

        $this->expectExceptionObject(ContentSystemException::elementTypeNotFound('CT:Unknown:Type'));
        $registry->get('CT:Unknown:Type');
    }

    #[TestDox('throws when two loaders register the same type name')]
    public function testCrossLoaderDuplicateThrowsWithSourceLabels(): void
    {
        $specA = $this->createSpec('CT:Content:Text', 'Text', 'core');
        $specB = $this->createSpec('CT:Content:Text', 'Text Dupe', 'plugin:MyPlugin');

        $loaderA = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loaderA->method('load')->willReturn([$specA]);

        $loaderB = static::createStub(AbstractContentSystemElementTypeLoader::class);
        $loaderB->method('load')->willReturn([$specB]);

        $registry = new ContentSystemElementTypeRegistry([$loaderA, $loaderB]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('CT:Content:Text', 'core', 'plugin:MyPlugin')
        );
        $registry->all();
    }

    private function createSpec(string $name, string $label, string $source = 'test'): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            $name,
            $label,
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
            $source,
        );
    }
}

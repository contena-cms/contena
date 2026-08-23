<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\ContentSystemException;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\CachedContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[CoversClass(CachedContentSystemElementTypeRegistry::class)]
class CachedContentSystemElementTypeRegistryTest extends TestCase
{
    #[TestDox('delegates to inner registry on cache miss and caches the result')]
    public function testAllDelegatesToInnerOnCacheMiss(): void
    {
        $spec = $this->createSpec('CT:Content:Text');
        $inner = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn(['CT:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $result = $registry->all();

        static::assertArrayHasKey('CT:Content:Text', $result);
        static::assertSame($spec, $result['CT:Content:Text']);
    }

    #[TestDox('returns cached result on second all() call without calling inner again')]
    public function testAllReturnsCachedResultOnSecondCall(): void
    {
        $spec = $this->createSpec('CT:Content:Text');
        $inner = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $inner->expects($this->once())->method('all')->willReturn(['CT:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $registry->all();
        $registry->all();
    }

    #[TestDox('returns true for a type present in the registry')]
    public function testHasReturnsTrueForCachedType(): void
    {
        $spec = $this->createSpec('CT:Content:Text');
        $inner = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn(['CT:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        static::assertTrue($registry->has('CT:Content:Text'));
    }

    #[TestDox('returns the specification for a known type')]
    public function testGetReturnsSpecificationFromCache(): void
    {
        $spec = $this->createSpec('CT:Content:Text');
        $inner = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn(['CT:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        static::assertSame($spec, $registry->get('CT:Content:Text'));
    }

    #[TestDox('forces re-delegation to inner registry after invalidation')]
    public function testInvalidateClearsCache(): void
    {
        $spec = $this->createSpec('CT:Content:Text');
        $inner = $this->createMock(AbstractContentSystemElementTypeRegistry::class);
        $inner->expects($this->exactly(2))->method('all')->willReturn(['CT:Content:Text' => $spec]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $registry->all();
        $registry->invalidate();
        $registry->all();
    }

    #[TestDox('returns false for an unknown type')]
    public function testHasReturnsFalseForUnknownType(): void
    {
        $inner = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn([]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        static::assertFalse($registry->has('CT:Unknown:Type'));
    }

    #[TestDox('throws for an unknown type')]
    public function testGetThrowsForUnknownType(): void
    {
        $inner = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $inner->method('all')->willReturn([]);

        $cache = new ArrayAdapter();
        $registry = new CachedContentSystemElementTypeRegistry($inner, $cache);

        $this->expectExceptionObject(ContentSystemException::elementTypeNotFound('CT:Unknown:Type'));
        $registry->get('CT:Unknown:Type');
    }

    private function createSpec(string $name): ContentSystemElementTypeSpecification
    {
        return new ContentSystemElementTypeSpecification(
            $name,
            $name,
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [],
            [],
            'test',
        );
    }
}

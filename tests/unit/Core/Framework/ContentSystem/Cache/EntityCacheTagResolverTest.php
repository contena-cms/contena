<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\ContentSystem\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @internal
 */
#[CoversClass(EntityCacheTagResolver::class)]
class EntityCacheTagResolverTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function knownEntityProvider(): \Generator
    {
        yield 'blog' => ['blog', 'blog-abc123'];
        yield 'category' => ['category', 'category-route-abc123'];
        yield 'landing page' => ['landing_page', 'landing-page-route-abc123'];
        yield 'content layout' => ['content_layout', 'content-layout-abc123'];
    }

    #[DataProvider('knownEntityProvider')]
    #[TestDox('resolves $entityName to cache tag with correct prefix')]
    public function testResolvesKnownEntityTypesToCacheTags(string $entityName, string $expectedTag): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $resolver = new EntityCacheTagResolver();

        static::assertSame($expectedTag, $resolver->resolve($definition, 'abc123'));
    }

    #[TestDox('returns null for unsupported entity type')]
    public function testReturnsNullForUnsupportedEntity(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('unsupported_entity');

        $resolver = new EntityCacheTagResolver();

        static::assertNull($resolver->resolve($definition, 'abc123'));
    }
}

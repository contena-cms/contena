<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Resource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Mcp\Resource\EntityListResource;

/**
 * @internal
 */
#[CoversClass(EntityListResource::class)]
class EntityListResourceTest extends TestCase
{
    public function testInvokeReturnsSortedEntitiesWithCorrectStructure(): void
    {
        $defBlog = static::createStub(EntityDefinition::class);
        $defBlog->method('getEntityName')->willReturn('blog');
        $defCategory = static::createStub(EntityDefinition::class);
        $defCategory->method('getEntityName')->willReturn('category');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$defBlog, $defCategory]);

        $resource = new EntityListResource($registry);
        $result = ($resource)();

        static::assertSame('contena://entities', $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertArrayHasKey('text', $result);

        $entities = json_decode($result['text'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(['blog', 'category'], $entities);
    }
}

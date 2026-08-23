<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;

/**
 * @internal
 */
#[CoversClass(ElasticsearchRegistry::class)]
class ElasticsearchRegistryTest extends TestCase
{
    public function testRegistry(): void
    {
        $definition = static::createStub(ElasticsearchBlogDefinition::class);
        $definition
            ->method('getEntityDefinition')
            ->willReturn(new BlogDefinition());

        $registry = new ElasticsearchRegistry([
            $definition,
        ]);

        static::assertTrue($registry->has('blog'));
        static::assertInstanceOf(ElasticsearchBlogDefinition::class, $registry->get('blog'));

        static::assertFalse($registry->has('category'));
        static::assertNull($registry->get('category'));

        static::assertSame(['blog'], $registry->getDefinitionNames());
    }
}

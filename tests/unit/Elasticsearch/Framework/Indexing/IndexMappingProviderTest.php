<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Indexing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingProvider;

/**
 * @internal
 */
#[CoversClass(IndexMappingProvider::class)]
class IndexMappingProviderTest extends TestCase
{
    public function testBuild(): void
    {
        $mapping = [
            'foo' => 'bar',
        ];

        $definition = static::createStub(AbstractElasticsearchDefinition::class);
        $definition->method('getMapping')->willReturn([
            'bar' => 'foo',
        ]);

        $provider = new IndexMappingProvider($mapping);

        static::assertEquals(
            [
                'foo' => 'bar',
                'bar' => 'foo',
            ],
            $provider->build(
                $definition,
                Context::createDefaultContext()
            )
        );
    }
}

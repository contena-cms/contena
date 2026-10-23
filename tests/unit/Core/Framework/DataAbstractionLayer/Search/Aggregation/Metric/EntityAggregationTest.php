<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(EntityAggregation::class)]
class EntityAggregationTest extends TestCase
{
    public function testEncode(): void
    {
        $aggregation = new EntityAggregation('foo', 'bar', 'blog');

        static::assertEquals([
            'name' => 'foo',
            'extensions' => [],
            'field' => 'bar',
            'entity' => 'blog',
            '_class' => EntityAggregation::class,
        ], $aggregation->jsonSerialize());
    }

    public function testClone(): void
    {
        $aggregation = new EntityAggregation('foo', 'bar', 'blog');
        $clone = clone $aggregation;

        static::assertSame('foo', $clone->getName());
        static::assertSame('bar', $clone->getField());
        static::assertSame($aggregation->jsonSerialize(), $clone->jsonSerialize());
    }
}

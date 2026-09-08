<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PrefixFilter::class)]
class PrefixFilterTest extends TestCase
{
    public function testEncode(): void
    {
        $filter = new PrefixFilter('foo', 'bar');

        static::assertEquals(
            [
                'field' => 'foo',
                'value' => 'bar',
                'isPrimary' => false,
                'resolved' => null,
                'extensions' => [],
                '_class' => PrefixFilter::class,
            ],
            $filter->jsonSerialize()
        );
    }

    public function testClone(): void
    {
        $filter = new PrefixFilter('foo', 'bar');
        $clone = clone $filter;

        static::assertSame($filter->jsonSerialize(), $clone->jsonSerialize());
        static::assertSame($filter->getField(), $clone->getField());
        static::assertSame($filter->getFields(), $clone->getFields());
        static::assertSame($filter->getValue(), $clone->getValue());
        static::assertNotSame($filter, $clone);
    }
}

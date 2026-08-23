<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;

/**
 * @internal
 */
#[CoversClass(NotEqualsFilter::class)]
class NotEqualsFilterTest extends TestCase
{
    public function testCreatesNotEqualsFilterTest(): void
    {
        $filter = new NotEqualsFilter('product.name', 'test');

        static::assertSame('product.name', $filter->getField());
        static::assertSame('test', $filter->getValue());
        static::assertCount(1, $filter->getQueries());
        static::assertSame(
            [
                new EqualsFilter('product.name', 'test')->jsonSerialize(),
            ],
            array_map(
                static fn (Filter $filter): array => $filter->jsonSerialize(),
                $filter->getQueries()
            )
        );
    }
}

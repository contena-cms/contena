<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Sort;

use Contena\Elasticsearch\Sort\CountSort;
use OpenSearchDSL\Sort\FieldSort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(CountSort::class)]
class CountSortTest extends TestCase
{
    public function testSerialize(): void
    {
        $sort = new CountSort('test.test', FieldSort::ASC);

        static::assertEquals(
            [
                'test._count' => [
                    'nested' => [
                        'path' => 'test',
                    ],
                    'missing' => 0,
                    'order' => 'asc',
                    'mode' => 'sum',
                ],
            ],
            $sort->toArray()
        );
    }
}

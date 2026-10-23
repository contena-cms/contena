<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Stock\StockData;
use Contena\Core\Content\Blog\Stock\StockDataCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(StockDataCollection::class)]
class StockDataCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $collection = new StockDataCollection([]);

        static::assertCount(0, $collection->all());
    }

    public function testGetStockForBlogId(): void
    {
        $collection = new StockDataCollection([]);

        static::assertNull($collection->getStockForBlogId('12345'));

        $stock1 = new StockData('12345', 10, true);
        $collection = new StockDataCollection([
            $stock1,
        ]);

        static::assertSame($stock1, $collection->getStockForBlogId('12345'));
        static::assertNull($collection->getStockForBlogId('23456'));
    }

    public function testAdd(): void
    {
        $collection = new StockDataCollection([]);

        static::assertCount(0, $collection->all());

        $stock1 = new StockData('12345', 10, true);

        $collection->add($stock1);

        static::assertCount(1, $collection->all());
        static::assertSame($stock1, $collection->getStockForBlogId('12345'));
    }
}

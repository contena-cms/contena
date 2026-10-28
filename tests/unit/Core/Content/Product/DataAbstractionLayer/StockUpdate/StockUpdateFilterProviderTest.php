<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StockUpdateFilterProvider::class)]
class StockUpdateFilterProviderTest extends TestCase
{
    public function testHandlesFilter(): void
    {
        $ids = ['id1', 'id2', 'id3'];

        $filter = new TestStockUpdateFilter(['id1', 'id2']);

        $provider = new StockUpdateFilterProvider([$filter]);

        static::assertSame(['id3'], $provider->filterProductIdsForStockUpdates($ids, Context::createDefaultContext()));
    }
}

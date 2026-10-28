<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Stock\StockLoadRequest;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(StockLoadRequest::class)]
class StockLoadRequestTest extends TestCase
{
    public function testStockRequest(): void
    {
        $stockRequest = new StockLoadRequest(['product-1', 'product-2']);

        static::assertSame(['product-1', 'product-2'], $stockRequest->productIds);
    }
}

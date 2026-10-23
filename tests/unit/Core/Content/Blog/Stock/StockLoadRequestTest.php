<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Stock\StockLoadRequest;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(StockLoadRequest::class)]
class StockLoadRequestTest extends TestCase
{
    public function testStockRequest(): void
    {
        $stockRequest = new StockLoadRequest(['blog-1', 'blog-2']);

        static::assertSame(['blog-1', 'blog-2'], $stockRequest->blogIds);
    }
}

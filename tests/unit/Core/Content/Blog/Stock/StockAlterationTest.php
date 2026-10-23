<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Stock\StockAlteration;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(StockAlteration::class)]
class StockAlterationTest extends TestCase
{
    public function testAccessors(): void
    {
        $alteration = new StockAlteration('12345', '67890', 10, 5);

        static::assertSame('12345', $alteration->lineItemId);
        static::assertSame('67890', $alteration->blogId);
        static::assertSame(10, $alteration->quantityBefore);
        static::assertSame(5, $alteration->newQuantity);
        static::assertSame(5, $alteration->quantityDelta());

        $alteration = new StockAlteration('12345', '67890', 3, 10);

        static::assertSame('12345', $alteration->lineItemId);
        static::assertSame('67890', $alteration->blogId);
        static::assertSame(3, $alteration->quantityBefore);
        static::assertSame(10, $alteration->newQuantity);
        static::assertSame(-7, $alteration->quantityDelta());
    }
}

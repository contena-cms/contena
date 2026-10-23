<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Aggregate\BlogPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;

/**
 * @internal
 */
#[CoversClass(BlogPriceCollection::class)]
class BlogPriceCollectionTest extends TestCase
{
    public function testSortByPriceUsesGrossInGrossTaxState(): void
    {
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_GROSS);

        $collection = new BlogPriceCollection([
            $this->createPrice('expensive-gross', gross: 200, net: 10),
            $this->createPrice('cheap-gross', gross: 100, net: 20),
        ]);

        $collection->sortByPrice($context);

        static::assertSame(['cheap-gross', 'expensive-gross'], $collection->getKeys());
    }

    public function testSortByPriceUsesNetInNetTaxState(): void
    {
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_NET);

        $collection = new BlogPriceCollection([
            $this->createPrice('expensive-gross', gross: 200, net: 10),
            $this->createPrice('cheap-gross', gross: 100, net: 20),
        ]);

        $collection->sortByPrice($context);

        static::assertSame(['expensive-gross', 'cheap-gross'], $collection->getKeys());
    }

}

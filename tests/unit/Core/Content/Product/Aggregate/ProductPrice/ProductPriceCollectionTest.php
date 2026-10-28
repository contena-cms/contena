<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Aggregate\ProductPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPriceCollection::class)]
class ProductPriceCollectionTest extends TestCase
{
    public function testSortByPriceUsesGrossInGrossTaxState(): void
    {
        $context = Context::createDefaultContext();
        $context->setTaxState(CartPrice::TAX_STATE_GROSS);

        $collection = new ProductPriceCollection([
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

        $collection = new ProductPriceCollection([
            $this->createPrice('expensive-gross', gross: 200, net: 10),
            $this->createPrice('cheap-gross', gross: 100, net: 20),
        ]);

        $collection->sortByPrice($context);

        static::assertSame(['expensive-gross', 'cheap-gross'], $collection->getKeys());
    }

    private function createPrice(string $id, float $gross, float $net): ProductPriceEntity
    {
        $price = new ProductPriceEntity();
        $price->setId($id);
        $price->setPrice(new PriceCollection([new Price(Uuid::randomHex(), $net, $gross, false)]));

        return $price;
    }
}

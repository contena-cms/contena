<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\AmountCalculator;
use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class TaxCalculationTypeTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param ItemBlueprint[] $items
     */
    #[DataProvider('calculationProvider')]
    public function testCalculation(
        array $items,
        CalculatedTaxCollection $horizontal,
        CalculatedTaxCollection $vertical,
        bool $useNet = false
    ): void {
        $context = static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $taxState = $useNet ? CartPrice::TAX_STATE_NET : CartPrice::TAX_STATE_GROSS;
        $context->setTaxState($taxState);

        $calculator = static::getContainer()->get(AmountCalculator::class);
        $cart = $this->createCart($items, $context);

        $context->getChannel()->setTaxCalculationType(ChannelDefinition::CALCULATION_TYPE_HORIZONTAL);
        $amount = $calculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );
        static::assertEquals($horizontal, $amount->getCalculatedTaxes());

        $context->getChannel()->setTaxCalculationType(ChannelDefinition::CALCULATION_TYPE_VERTICAL);
        $amount = $calculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );
        static::assertEquals($vertical, $amount->getCalculatedTaxes());
    }

    /**
     * @return array<array{0: ItemBlueprint[], 1: CalculatedTaxCollection, 2: CalculatedTaxCollection, 3?: bool}>
     */
    public static function calculationProvider()
    {
        return [
            [
                [
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(0.69, 19, 4.29)]),
                new CalculatedTaxCollection([new CalculatedTax(0.68, 19, 4.29)]),
            ],
            [
                [
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(9.57, 19, 59.97)]),
                new CalculatedTaxCollection([new CalculatedTax(9.58, 19, 59.97)]),
            ],
            [
                [
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 7),
                    new ItemBlueprint(19.99, 1, 7),
                    new ItemBlueprint(19.99, 1, 7),
                ],
                new CalculatedTaxCollection([
                    new CalculatedTax(9.57, 19, 59.97),
                    new CalculatedTax(3.93, 7, 59.97),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(9.58, 19, 59.97),
                    new CalculatedTax(3.92, 7, 59.97),
                ]),
            ],

            // net calculations
            [
                [
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(0.81, 19, 4.29)]),
                new CalculatedTaxCollection([new CalculatedTax(0.82, 19, 4.29)]),
                true,
            ],
            // net calculation for different price
            [
                [
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(11.4, 19, 59.97)]),
                new CalculatedTaxCollection([new CalculatedTax(11.39, 19, 59.97)]),
                true,
            ],

            // net calculation for tax mixed carts
            [
                [
                    new ItemBlueprint(13.31, 1, 7),
                    new ItemBlueprint(13.31, 1, 7),
                    new ItemBlueprint(13.31, 1, 7),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                ],
                new CalculatedTaxCollection([
                    new CalculatedTax(2.79, 7, 39.93),
                    new CalculatedTax(0.81, 19, 4.29),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(2.80, 7, 39.93),
                    new CalculatedTax(0.82, 19, 4.29),
                ]),
                true,
            ],
        ];
    }

    /**
     * @param ItemBlueprint[] $items
     */
    private function createCart(array $items, ChannelContext $context): Cart
    {
        $cart = static::getContainer()
            ->get(CartService::class)
            ->getCart(Uuid::randomHex(), $context);

        foreach ($items as $i => $item) {
            $lineItem = new LineItem('item-' . $i, 'test', 'item-' . $i, $item->quantity);

            $definition = new QuantityPriceDefinition($item->price, new TaxRuleCollection([new TaxRule($item->taxRate)]), $item->quantity);

            $lineItem->setPriceDefinition($definition);

            $price = static::getContainer()
                ->get(QuantityPriceCalculator::class)
                ->calculate($definition, $context);

            $lineItem->setPrice($price);

            $cart->add($lineItem);
        }

        return $cart;
    }
}

/**
 * @internal
 */
class ItemBlueprint
{
    public int $quantity;

    public float $price;

    public int $taxRate;

    public function __construct(
        float $price,
        int $quantity,
        int $taxRate
    ) {
        $this->quantity = $quantity;
        $this->price = $price;
        $this->taxRate = $taxRate;
    }
}

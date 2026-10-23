<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Contena\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class CartScopeDiscountPackagerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param LineItem[] $items
     * @param array<string> $expected
     */
    #[DataProvider('buildPackagesProvider')]
    public function testBuildPackages(array $items, array $expected): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection($items));

        $packager = static::getContainer()->get(CartScopeDiscountPackager::class);

        $context = static::createStub(ChannelContext::class);

        $discount = new DiscountLineItem('test', new QuantityPriceDefinition(10, new TaxRuleCollection([]), 1), [
            'discountScope' => 'scope',
            'discountType' => 'type',
            'filter' => [
                'considerAdvancedRules' => true,
            ],
        ], null);

        $packages = $packager->getMatchingItems($discount, $cart, $context);

        $ids = $packages->first()?->getMetaData()->map(static fn (LineItemQuantity $item) => $item->getLineItemId());

        static::assertSame($expected, $ids);
    }

    public static function buildPackagesProvider(): \Generator
    {
        $stackable = new LineItem('stackable', LineItem::BLOG_LINE_ITEM_TYPE, null, 1);
        $stackable->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $stackable->setStackable(true);

        $other = new LineItem('other', LineItem::BLOG_LINE_ITEM_TYPE, null, 2);
        $other->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $other->setStackable(true);

        $none = new LineItem('none', LineItem::BLOG_LINE_ITEM_TYPE, null, 1);
        $none->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $none->setStackable(false);

        $credit = new LineItem('credit', LineItem::CREDIT_LINE_ITEM_TYPE, null, 1);
        $credit->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $credit->setStackable(true);

        yield 'Items will be splitted' => [
            [$stackable, $other],
            ['stackable', 'other', 'other'],
        ];

        yield 'None stackable will not be considered' => [
            [$stackable, $other, $none],
            ['stackable', 'other', 'other'],
        ];

        yield 'None stackable items will not be splitted' => [
            [$stackable, $other, $credit],
            ['stackable', 'other', 'other'],
        ];
    }
}

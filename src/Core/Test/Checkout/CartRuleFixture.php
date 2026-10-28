<?php declare(strict_types=1);

namespace Contena\Core\Test\Checkout;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Framework\Log\Package;

/**
 * Builds the line items and carts the cart rule tests match against. Every method returns a fresh object,
 * there is no shared state, so it is a plain static helper instead of a trait mixed into each test class.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
final class CartRuleFixture
{
    /**
     * @return \Generator<string, array{non-empty-string, bool}>
     */
    public static function lineItemTypeProvider(): \Generator
    {
        foreach (self::lineItemTypes() as $name => $type) {
            yield $name . ' via line item scope' => [$type, true];
            yield $name . ' via cart scope' => [$type, false];
        }
    }

    /**
     * @return \Generator<string, array{non-empty-string, bool, bool}>
     */
    public static function lineItemWithoutProductDataProvider(): \Generator
    {
        foreach (self::lineItemTypes() as $name => $type) {
            if ($type === LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }

            yield $name . ' via line item scope' => [$type, true, true];
            yield $name . ' via cart scope' => [$type, false, false];
        }
    }







    /**
     * @return array<string, non-empty-string>
     */
    private static function lineItemTypes(): array
    {
        return [
            'product' => LineItem::PRODUCT_LINE_ITEM_TYPE,
            'custom' => LineItem::CUSTOM_LINE_ITEM_TYPE,
            'credit' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'plugin item' => 'my-plugin-item',
            'container' => LineItem::CONTAINER_LINE_ITEM,
            'customized products option' => 'customized-products-option',
            'customized products option value' => 'option-values',
        ];
    }
}

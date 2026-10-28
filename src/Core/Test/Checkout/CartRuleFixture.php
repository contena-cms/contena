<?php declare(strict_types=1);

namespace Contena\Core\Test\Checkout;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ListPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

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

    public static function createLineItem(
        string $type = LineItem::PRODUCT_LINE_ITEM_TYPE,
        int $quantity = 1,
        ?string $referenceId = null
    ): LineItem {
        return new LineItem(Uuid::randomHex(), $type, $referenceId, $quantity);
    }

    public static function createDigitalProductLineItem(): LineItem
    {
        return self::createLineItem()->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, ProductDefinition::TYPE_DIGITAL);
    }

    public static function createLineItemWithDeliveryInfo(
        bool $freeDelivery,
        int $quantity = 1,
        ?float $weight = 50.0,
        ?float $height = null,
        ?float $width = null,
        ?float $length = null,
        int $stock = 9999
    ): LineItem {
        return self::createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $quantity)->setDeliveryInformation(
            new DeliveryInformation(
                $stock,
                $weight,
                $freeDelivery,
                null,
                (new DeliveryTime())->assign([
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'weeks',
                    'name' => '1-3 weeks',
                ]),
                $height,
                $width,
                $length
            )
        );
    }

    public static function createContainerLineItem(LineItemCollection $childLineItemCollection): LineItem
    {
        return self::createLineItem('container-type')->setChildren($childLineItemCollection);
    }

    public static function createLineItemWithPrice(string $type, float $price, ?ListPrice $listPrice = null): LineItem
    {
        return self::createLineItem($type)->setPrice(
            new CalculatedPrice(
                $price,
                $price,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                1,
                null,
                $listPrice
            )
        );
    }

    public static function createCart(LineItemCollection $lineItemCollection): Cart
    {
        $cart = new Cart(Uuid::randomHex());
        $cart->addLineItems($lineItemCollection);

        return $cart;
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

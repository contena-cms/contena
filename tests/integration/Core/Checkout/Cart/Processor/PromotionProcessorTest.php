<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Processor;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<LineItem> $items
     */
    #[DataProvider('processorProvider')]
    public function testProcessor(array $items, CartPrice $cartPrice, ?Error $expectedError): void
    {
        $processor = static::getContainer()->get(PromotionProcessor::class);

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection($items));
        $cart->setPrice($cartPrice);

        $new = new Cart('after');
        $new->setLineItems(new LineItemCollection($items));
        $new->setPrice($cartPrice);

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection(
            [new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE, Uuid::randomHex(), 1)]
        ));

        $processor->process($data, $cart, $new, $context, new CartBehavior());

        if ($expectedError === null) {
            static::assertCount(0, $new->getErrors());
        } else {
            static::assertCount(1, $new->getErrors()->filterInstance($expectedError::class));
        }
    }

    #[DataProvider('processorPromotionTypeProvider')]
    public function testProcessorPromotionType(LineItem $promotionItem, bool $expectedError): void
    {
        $processor = static::getContainer()->get(PromotionProcessor::class);

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $items = [
            new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE, Uuid::randomHex(), 1),
        ];
        $cartPrice = new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS);

        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection($items));
        $cart->setPrice($cartPrice);

        $new = new Cart('after');
        $new->setLineItems(new LineItemCollection($items));
        $new->setPrice($cartPrice);

        $data = new CartDataCollection();
        $data->set(PromotionProcessor::DATA_KEY, new LineItemCollection(
            [$promotionItem]
        ));

        $processor->process($data, $cart, $new, $context, new CartBehavior());

        if ($expectedError) {
            static::assertCount(1, $new->getErrors()->filterInstance(PromotionsOnCartPriceZeroError::class));
        } else {
            static::assertCount(0, $new->getErrors());
        }
    }

    public static function processorProvider(): \Generator
    {
        $context = Generator::generateChannelContext();
        $context->setTaxState(CartPrice::TAX_STATE_GROSS);
        $context->setItemRounding(new CashRoundingConfig(2, 0.01, true));

        yield 'Do not process discounts when cart is zero' => [
            [new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE, Uuid::randomHex(), 1)],
            new CartPrice(0, 0, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            new PromotionsOnCartPriceZeroError([]),
        ];

        yield 'Do process discounts when cart is not zero' => [
            [new LineItem(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE, Uuid::randomHex(), 1)],
            new CartPrice(100, 100, 0, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS),
            null,
        ];
    }

    public static function processorPromotionTypeProvider(): \Generator
    {
        $context = Generator::generateChannelContext();
        $context->setTaxState(CartPrice::TAX_STATE_GROSS);
        $context->setItemRounding(new CashRoundingConfig(2, 0.01, true));

        yield 'Do not add error when cart is zero if promotion is global' => [
            (new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE, Uuid::randomHex(), 1))
            ->setPayload(['promotionCodeType' => 'global']),
            false,
        ];

        yield 'Do add error when cart is zero if promotion is not global' => [
            (new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE, Uuid::randomHex(), 1))
                ->setPayload(['promotionCodeType' => 'fixed']),
            true,
        ];
    }
}

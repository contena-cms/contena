<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\CartDataCollectorInterface;
use Contena\Core\Checkout\Cart\CartProcessorInterface;
use Contena\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\AmountCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Processor;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Cart\Transaction\TransactionProcessor;
use Contena\Core\Checkout\Cart\Validator;
use Contena\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Contena\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    private AbstractChannelContextFactory $factory;

    private ChannelContext $context;

    private Processor $processor;

    protected function setUp(): void
    {
        $this->processor = static::getContainer()->get(Processor::class);
        $this->factory = static::getContainer()->get(ChannelContextFactory::class);
        $this->context = $this->factory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testDeliveryCreatedForDeliverableLineItem(): void
    {
        $cart = new Cart('test');

        $id = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $blog = [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'blogNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->addTaxDataToChannel($this->context, $tax);

        $cart->add(
            (new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getLineItems());
        static::assertTrue($calculated->has($id));
        $item = $calculated->get($id);
        static::assertInstanceOf(LineItem::class, $item);
        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice());
        static::assertSame(119.99, $item->getPrice()->getTotalPrice());

        static::assertCount(1, $calculated->getDeliveries());

        /** @var Delivery $delivery */
        $delivery = $calculated->getDeliveries()->first();
        static::assertTrue($delivery->getPositions()->getLineItems()->has($id));
    }

    public function testExtensionsAreMergedEarly(): void
    {
        $extension = new class extends Struct {
        };

        $cart = new Cart('bar');
        $cart->addExtension('unit-test', $extension);

        $processor = new Processor(
            new Validator([]),
            static::createStub(AmountCalculator::class),
            static::createStub(TransactionProcessor::class),
            [
                new class implements CartProcessorInterface {
                    public function process(CartDataCollection $data, Cart $original, Cart $toCalculate, ChannelContext $context, CartBehavior $behavior): void
                    {
                        TestCase::assertNotNull($original->getExtension('unit-test'));
                        TestCase::assertNotNull($toCalculate->getExtension('unit-test'));
                        TestCase::assertSame($original->getExtension('unit-test'), $toCalculate->getExtension('unit-test'));
                    }
                },
            ],
            [],
            static::createStub(ScriptExecutor::class)
        );

        $newCart = $processor->process($cart, $this->context, new CartBehavior());

        static::assertSame($extension, $newCart->getExtension('unit-test'));
    }

    public function testCalculatedCreditTaxesIncludeCustomItemTax(): void
    {
        $cart = new Cart('test');

        $blogId = Uuid::randomHex();
        $customItemId = Uuid::randomHex();
        $creditId = Uuid::randomHex();

        $taxForBlogItem = 10;

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => $taxForBlogItem, 'name' => 'test'];
        $blog = [
            'id' => $blogId,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 220, 'net' => 200, 'linked' => false],
            ],
            'blogNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->addTaxDataToChannel($this->context, $tax);

        $taxForCustomItem = 20;

        $blogLineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId, 1);
        $taxRulesCustomItem = new TaxRuleCollection([new TaxRule($taxForCustomItem)]);
        $customLineItem = (new LineItem($customItemId, LineItem::CUSTOM_LINE_ITEM_TYPE, $customItemId, 1))
            ->setLabel('custom')
            ->setPriceDefinition(new QuantityPriceDefinition(200, $taxRulesCustomItem, 2));

        $creditLineItem = (new LineItem($creditId, LineItem::CREDIT_LINE_ITEM_TYPE, $creditId, 1))
            ->setLabel('credit')
            ->setPriceDefinition(new AbsolutePriceDefinition(-100));

        $cart->addLineItems(new LineItemCollection([$blogLineItem, $customLineItem, $creditLineItem]));

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(3, $calculated->getLineItems());

        $creditLineItem = $calculated->getLineItems()->filterType(LineItem::CREDIT_LINE_ITEM_TYPE)->first();
        static::assertInstanceOf(LineItem::class, $creditLineItem);
        static::assertInstanceOf(CalculatedPrice::class, $creditLineItem->getPrice());
        static::assertCount(2, $creditCalculatedTaxes = $creditLineItem->getPrice()->getCalculatedTaxes()->getElements());

        $calculatedTaxForCustomItem = array_filter($creditCalculatedTaxes, static fn (CalculatedTax $tax) => (int) $tax->getTaxRate() === $taxForCustomItem);

        static::assertNotCount(0, $calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForBlogItem = array_filter($creditCalculatedTaxes, static fn (CalculatedTax $tax) => (int) $tax->getTaxRate() === $taxForBlogItem);

        static::assertNotCount(0, $calculatedTaxForBlogItem);
        static::assertCount(1, $calculatedTaxForBlogItem);
    }

    public function testShippingCostIsCalculatedWithCustomItemOnly(): void
    {
        $cart = new Cart('test');

        $customItemId = Uuid::randomHex();

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 10, 'name' => 'test'];

        $this->addTaxDataToChannel($this->context, $tax);

        $taxForCustomItem = 20;
        $taxRulesCustomItem = new TaxRuleCollection([new TaxRule($taxForCustomItem)]);

        $customLineItem = (new LineItem($customItemId, LineItem::CUSTOM_LINE_ITEM_TYPE, $customItemId, 1))
            ->setLabel('custom')
            ->setPriceDefinition(new QuantityPriceDefinition(200, $taxRulesCustomItem, 2));

        $cart->add($customLineItem);

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        $delivery = $calculated->getDeliveries()->first();

        static::assertInstanceOf(Delivery::class, $delivery);

        $shippingCalculatedTaxes = $delivery->getShippingCosts()->getCalculatedTaxes()->first();
        static::assertInstanceOf(CalculatedTax::class, $shippingCalculatedTaxes);
        static::assertEquals($taxForCustomItem, $shippingCalculatedTaxes->getTaxRate());
    }

    public function testShippingCostCalculatedTaxesIncludeCustomItemTax(): void
    {
        $cart = new Cart('test');

        $blogId = Uuid::randomHex();
        $customItemId = Uuid::randomHex();

        $taxForCustomItem = 20;
        $taxForBlogItem = 10;

        $tax = ['id' => Uuid::randomHex(), 'taxRate' => $taxForBlogItem, 'name' => 'test'];

        $blog = [
            'id' => $blogId,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 220, 'net' => 200, 'linked' => false],
            ],
            'blogNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->addTaxDataToChannel($this->context, $tax);

        $taxRulesCustomItem = new TaxRuleCollection([new TaxRule($taxForCustomItem)]);

        $blogLineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId, 1);
        $customLineItem = (new LineItem($customItemId, LineItem::CUSTOM_LINE_ITEM_TYPE, $customItemId, 1))
            ->setLabel('custom')
            ->setPriceDefinition(new QuantityPriceDefinition(200, $taxRulesCustomItem, 2));

        $cart->add($blogLineItem);
        $cart->add($customLineItem);

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $calculated->getDeliveries());

        $delivery = $calculated->getDeliveries()->first();

        static::assertInstanceOf(Delivery::class, $delivery);
        static::assertCount(2, $shippingCalculatedTaxes = $delivery->getShippingCosts()->getCalculatedTaxes()->getElements());

        $calculatedTaxForCustomItem = array_filter($shippingCalculatedTaxes, static fn (CalculatedTax $tax) => (int) $tax->getTaxRate() === $taxForCustomItem);

        static::assertNotCount(0, $calculatedTaxForCustomItem);
        static::assertCount(1, $calculatedTaxForCustomItem);

        $calculatedTaxForBlogItem = array_filter($shippingCalculatedTaxes, static fn (CalculatedTax $tax) => (int) $tax->getTaxRate() === $taxForBlogItem);

        static::assertNotCount(0, $calculatedTaxForBlogItem);
        static::assertCount(1, $calculatedTaxForBlogItem);
    }

    public function testPersistentErrors(): void
    {
        $cart = new Cart(Uuid::randomHex());

        $cart->addErrors(new NonePersistentError(), new PersistentError());

        $cart = static::getContainer()->get(Processor::class)
            ->process($cart, $this->context, new CartBehavior());

        static::assertCount(1, $cart->getErrors());
        static::assertInstanceOf(PersistentError::class, $cart->getErrors()->first());

        $error = $cart->getErrors()->first();
        static::assertSame('persistent', $error->getId());
        static::assertSame('persistent', $error->getMessageKey());
    }

    public function testCartHasErrorDataAddedFromPromotionProcessor(): void
    {
        $originalCart = new Cart(Uuid::randomHex());

        $id = Uuid::randomHex();
        $tax = ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'];

        $blog = $this->createDummyBlog($id, $tax);

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->addTaxDataToChannel($this->context, $tax);

        $originalCart->add(
            (new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );
        $originalCart->add(
            (new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, '', 1))
                ->setLabel('Discount 15%')
        );
        $originalCart->add(
            (new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE, '', 1))
                ->setLabel('Discount 10%')
        );

        $cart = $this->processor->process($originalCart, $this->context, new CartBehavior());
        static::assertCount(3, $cart->getErrors());

        foreach ($cart->getErrors() as $error) {
            if ($error instanceof ShippingMethodBlockedError) {
                continue;
            }
            static::assertInstanceOf(AutoPromotionNotFoundError::class, $error);
        }
    }

    public function testProcessKeepsPersistedStateOfOriginalCart(): void
    {
        $cart = new Cart('test');

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());
        static::assertFalse($calculated->isPersisted());

        $cart->setPersisted(true);

        $calculated = $this->processor->process($cart, $this->context, new CartBehavior());
        static::assertTrue($calculated->isPersisted());
    }

    public function testProcessorsAndCollectorsAreSkippedIfCartIsEmpty(): void
    {
        $cart = new Cart('test');

        $collector = $this->createMock(CartDataCollectorInterface::class);
        $collector->expects($this->never())
            ->method('collect');

        $processorMock = $this->createMock(CartProcessorInterface::class);
        $processorMock->expects($this->never())
            ->method('process');

        $processor = new Processor(
            static::getContainer()->get(Validator::class),
            static::getContainer()->get(AmountCalculator::class),
            static::getContainer()->get(TransactionProcessor::class),
            [$processorMock],
            [$collector],
            static::getContainer()->get(ScriptExecutor::class)
        );
        $processor->process($cart, $this->context, new CartBehavior());
    }

    /**
     * @param array<string|int, mixed|null> $tax
     *
     * @return array<string, mixed|null>
     */
    private function createDummyBlog(string $id, array $tax, int $stock = 10): array
    {
        return [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'blogNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => $tax,
            'stock' => $stock,
            'isCloseout' => true,
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }
}

/**
 * @internal
 */
class PersistentError extends Error
{
    public function getId(): string
    {
        return 'persistent';
    }

    public function getMessageKey(): string
    {
        return 'persistent';
    }

    public function getLevel(): int
    {
        return 1;
    }

    public function blockOrder(): bool
    {
        return false;
    }

    /**
     * @return array<string|int, mixed|null>
     */
    public function getParameters(): array
    {
        return [];
    }

    public function isPersistent(): bool
    {
        return true;
    }
}

/**
 * @internal
 */
class NonePersistentError extends PersistentError
{
    public function getId(): string
    {
        return 'none-' . parent::getId();
    }

    public function getMessageKey(): string
    {
        return 'none-' . parent::getMessageKey();
    }

    public function isPersistent(): bool
    {
        return false;
    }
}

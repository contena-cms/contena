<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Contena\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Contena\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class DeliveryProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $shippingMethodCriteria = new Criteria([$this->channelContext->getShippingMethod()->getId()]);
        $shippingMethodCriteria->addAssociation('media');
        $shippingMethodCriteria->addAssociation('deliveryTime');

        $this->channelContext->assign([
            'shippingMethod' => static::getContainer()->get('shipping_method.repository')->search($shippingMethodCriteria, $this->channelContext->getContext())->getEntities()->first(),
        ]);

        $shippingMethodPriceEntity = new ShippingMethodPriceEntity();
        $shippingMethodPriceEntity->setUniqueIdentifier('test');
        $shippingMethodPriceEntity->setCurrencyPrice(new PriceCollection([new Price(Defaults::CURRENCY, 5, 5, false)]));

        $this->channelContext->getShippingMethod()->setPrices(new ShippingMethodPriceCollection([$shippingMethodPriceEntity]));
    }

    public function testProcessShouldRecalculateAll(): void
    {
        $deliveryProcessor = static::getContainer()->get(DeliveryProcessor::class);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->channelContext->getShippingMethod()->getId()),
            $this->channelContext->getShippingMethod()
        );
        $originalCart = new Cart('original');
        $calculatedCart = new Cart('calculated');

        $lineItem = new LineItem('test', LineItem::BLOG_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));
        $lineItem->setShippingCostAware(true);

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));

        $cartBehavior = new CartBehavior();

        static::assertCount(0, $calculatedCart->getDeliveries());

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->channelContext, $cartBehavior);

        // Deliveries were built
        static::assertCount(1, $calculatedCart->getDeliveries());

        // Price was recalculated
        static::assertNotNull($calculatedCart->getDeliveries()->first());
        static::assertSame(5.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());

        // Tax was recalculated
        static::assertNotNull($calculatedCart->getDeliveries()->first());
        static::assertCount(1, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes());
        static::assertNotNull($calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes()->first());
        static::assertSame(5.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes()->first()->getPrice());
    }

    public function testMultiProcessWhenShippingCostEditedWithCurrencyFactor(): void
    {
        $factor = 1.1;
        $this->channelContext->getContext()->assign(['currencyFactor' => $factor]);
        $deliveryProcessor = static::getContainer()->get(DeliveryProcessor::class);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->channelContext->getShippingMethod()->getId()),
            $this->channelContext->getShippingMethod()
        );

        $originalCart = new Cart('original');
        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setName('Express');
        $shippingMethod->addTranslated('name', 'Express');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $originalCart->setDeliveries(new DeliveryCollection([$delivery]));

        $calculatedCart = new Cart('calculated');

        $lineItem = new LineItem('test', LineItem::BLOG_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));
        $cartBehavior = new CartBehavior([DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true]);

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->channelContext, $cartBehavior);
        $originalCart = $calculatedCart;
        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->channelContext, $cartBehavior);

        // Price was recalculated
        static::assertNotNull($originalCart->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS));
        static::assertNotNull($originalCart->getDeliveries()->first());
        static::assertSame(10.0, $originalCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());
    }

    public function testDeliveriesContainDiscountButSkipRecalculation(): void
    {
        $deliveryProcessor = static::getContainer()->get(DeliveryProcessor::class);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->channelContext->getShippingMethod()->getId()),
            $this->channelContext->getShippingMethod()
        );

        $originalCart = new Cart('original');
        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setName('Express');
        $shippingMethod->addTranslated('name', 'Express');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $deliveryDiscount = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(-10, -10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $originalCart->setDeliveries(new DeliveryCollection([$delivery, $deliveryDiscount]));

        $calculatedCart = new Cart('calculated');

        $lineItem = new LineItem('test', LineItem::BLOG_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));
        $cartBehavior = new CartBehavior([DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true]);

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->channelContext, $cartBehavior);
        $originalCart = $calculatedCart;
        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->channelContext, $cartBehavior);

        // Price was recalculated
        static::assertNotNull($originalCart->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS));
        static::assertNotNull($originalCart->getDeliveries()->first());
        static::assertCount(1, $originalCart->getDeliveries());
        static::assertInstanceOf(Delivery::class, $originalCart->getDeliveries()->first());
        static::assertSame(10.0, $originalCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());
    }

    private function generateDeliveryTimeDummy(): DeliveryTimeEntity
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setMin(1);
        $deliveryTime->setMax(3);
        $deliveryTime->setUnit(DeliveryTimeEntity::DELIVERY_TIME_DAY);

        return $deliveryTime;
    }
}

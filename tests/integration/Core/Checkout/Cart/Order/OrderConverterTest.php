<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Order\IdStruct;
use Contena\Core\Checkout\Cart\Order\OrderConversionContext;
use Contena\Core\Checkout\Cart\Order\OrderConverter;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Contena\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
class OrderConverterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private OrderConverter $orderConverter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderConverter = static::getContainer()->get(OrderConverter::class);
    }

    public function testConvertToOrderAndSetPrimary(): void
    {
        $cartToken = Uuid::randomHex();
        $cart = $this->getCart($cartToken);
        $context = Generator::generateChannelContext(user: $this->getUser());

        $convertedOrder = $this->orderConverter->convertToOrder($cart, $context, new OrderConversionContext());

        static::assertSame($convertedOrder['deliveries'][0]['id'], $cartToken);
        static::assertNotNull($convertedOrder['transactions'][0]['id']);
    }

    public function testThatPrimaryDeliveryIsPositionedFirst(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $firstDeliveryId = Uuid::randomHex();
        $primaryOrderDeliveryId = Uuid::randomHex();
        $thirdDeliveryId = Uuid::randomHex();
        $fourthDeliveryId = Uuid::randomHex();

        $orderLineItem = new OrderLineItemEntity();
        $orderLineItem->setIdentifier('order-line-item-identifier');
        $orderLineItem->setId('order-line-item-id');
        $orderLineItem->setQuantity(1);
        $orderLineItem->setType(LineItem::BLOG_LINE_ITEM_TYPE);
        $orderLineItem->setLabel('order-line-item-label');
        $orderLineItem->setGood(true);
        $orderLineItem->setRemovable(false);
        $orderLineItem->setStackable(true);

        $deliveryCollection = new OrderDeliveryCollection();
        $deliveryCollection->add($this->getOrderDelivery($firstDeliveryId, $orderLineItem));
        $deliveryCollection->add($this->getOrderDelivery($primaryOrderDeliveryId, $orderLineItem));
        $deliveryCollection->add($this->getOrderDelivery($thirdDeliveryId, $orderLineItem));
        $deliveryCollection->add($this->getOrderDelivery($fourthDeliveryId, $orderLineItem));

        $order = new OrderEntity();
        $order->setPrimaryOrderDeliveryId($primaryOrderDeliveryId);
        $order->setId(Uuid::randomHex());
        $order->setOrderNumber('10034');
        $order->setLineItems(new OrderLineItemCollection([$orderLineItem]));
        $order->setPrice(new CartPrice(19.5, 19.5, 19.5, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_FREE));
        $order->setDeliveries($deliveryCollection);

        $context = Generator::generateChannelContext(user: $this->getUser());

        $convertedCart = $this->orderConverter->convertToCart($order, $context->getContext());

        static::assertNotNull($convertedCart->getDeliveries()->first());
        static::assertSame($primaryOrderDeliveryId, $convertedCart->getDeliveries()->first()->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertNotNull($convertedCart->getDeliveries()->getAt(1));
        static::assertSame($firstDeliveryId, $convertedCart->getDeliveries()->getAt(1)->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertNotNull($convertedCart->getDeliveries()->getAt(2));
        static::assertSame($thirdDeliveryId, $convertedCart->getDeliveries()->getAt(2)->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
        static::assertNotNull($convertedCart->getDeliveries()->getAt(3));
        static::assertSame($fourthDeliveryId, $convertedCart->getDeliveries()->getAt(3)->getExtensionOfType(OrderConverter::ORIGINAL_ID, IdStruct::class)?->getId());
    }

    private function getUser(): UserEntity
    {
        $userAddress = new UserAddressEntity();
        $userAddress->setId(Uuid::randomHex());
        $userAddress->setFirstName('Test');
        $userAddress->setLastName('Test');
        $userAddress->setStreet('Test Street');
        $userAddress->setCity('Test City');
        $userAddress->setCountryId($this->getValidCountryId());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setEmail('test@test.com');
        $user->setFirstName('Test');
        $user->setLastName('Test');
        $user->setUserNumber(Uuid::randomHex());
        $user->setActiveBillingAddress($userAddress);

        return $user;
    }

    private function getCart(string $id): Cart
    {
        $cart = new Cart('token');

        $cart->setTransactions(
            new TransactionCollection([
                $this->getTransaction(0.5),
                $this->getTransaction(100),
            ])
        );

        $cart->setDeliveries(
            new DeliveryCollection([
                $this->getDelivery(Uuid::randomHex(), 1.0),
                $this->getDelivery($id, 10.5),
            ])
        );

        return $cart;
    }

    private function getTransaction(float $cost): Transaction
    {
        return new Transaction(
            new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection()),
            Uuid::randomHex()
        );
    }

    private function getDelivery(string $id, float $cost): Delivery
    {
        $billingAddress = new UserAddressEntity();
        $billingAddress->setId(Uuid::randomHex());
        $billingAddress->setFirstName('foo');
        $billingAddress->setLastName('bar');
        $billingAddress->setStreet('street');
        $billingAddress->setCity('city');
        $billingAddress->setCountryId(Uuid::randomHex());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId(Uuid::randomHex());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable('now'), new \DateTimeImmutable('now')),
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, $billingAddress),
            new CalculatedPrice($cost, $cost, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $delivery->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct($id));

        return $delivery;
    }

    private function getOrderDelivery(string $id, OrderLineItemEntity $orderLineItem): OrderDeliveryEntity
    {
        $shippingAddress = new OrderAddressEntity();
        $shippingAddress->setId(Uuid::randomHex());
        $shippingAddress->setCountry(new CountryEntity());

        $orderDeliveryPosition = new OrderDeliveryPositionEntity();
        $orderDeliveryPosition->setId('order-delivery-position-id-1');
        $orderDeliveryPosition->setOrderLineItem($orderLineItem);
        $orderDeliveryPosition->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId($id);
        $orderDelivery->setOrderId('order-id');
        $orderDelivery->setOrderVersionId('order-version-id');
        $orderDelivery->setShippingMethodId('shipping-method-id');
        $orderDelivery->setShippingMethod(new ShippingMethodEntity());
        $orderDelivery->setShippingOrderAddressId('shipping-order-address-id');
        $orderDelivery->setShippingOrderAddress($shippingAddress);
        $orderDelivery->setShippingOrderAddressVersionId('shipping-order-address-version-id');
        $orderDelivery->setShippingDateEarliest(new \DateTimeImmutable('now'));
        $orderDelivery->setShippingDateLatest(new \DateTimeImmutable('now'));
        $orderDelivery->setShippingCosts(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $orderDelivery->setTrackingCodes([]);
        $orderDelivery->setPositions(new OrderDeliveryPositionCollection([$orderDeliveryPosition]));
        $orderDelivery->setStateId('state-id');

        return $orderDelivery;
    }
}

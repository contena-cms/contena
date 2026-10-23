<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Order;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\Delivery\DeliveryCalculator;
use Contena\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\PromotionLineItemFactory;
use Contena\Core\Checkout\Cart\Order\OrderConverter;
use Contena\Core\Checkout\Cart\Order\OrderPersister;
use Contena\Core\Checkout\Cart\Order\RecalculationService;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Processor;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\Cart\Error\PromotionDiscountUnknownConditionError;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Cart\BlogCartProcessor;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\System\DeliveryTime\DeliveryTimeEntity;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Contena\Core\Test\Stub\Rule\TrueRule;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class RecalculationServiceTest extends TestCase
{
    use AdminApiTestBehaviour;
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    protected ChannelContext $channelContext;

    protected Context $context;

    protected string $userId;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private EntityRepository $shippingMethodRepository;

    /**
     * @var EntityRepository<OrderDeliveryCollection>
     */
    private EntityRepository $orderDeliveryRepository;

    protected function setUp(): void
    {
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
        $this->orderDeliveryRepository = static::getContainer()->get('order_delivery.repository');

        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->userId = $this->createUser();
        $shippingMethodId = $this->createShippingMethod($priceRuleId);
        $paymentMethodId = $this->createPaymentMethod($priceRuleId);
        $this->addCountriesToChannel([$this->getValidCountryIdWithTaxes()]);
        $this->channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->userId,
                ChannelContextService::SHIPPING_METHOD_ID => $shippingMethodId,
                ChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );

        $this->channelContext->setRuleIds([$priceRuleId]);
    }

    public function testCreateVersionSucceedsForOrderWithUnregisteredRuleConditionInPriceDefinition(): void
    {
        // Simulates an order whose price-definition filter references a rule condition contributed by a
        // plugin that has since been uninstalled. Opening such an order in the Administration creates an
        // order version (a clone), which re-encodes the price definition - this must not fail.
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // stable serialized values; avoids importing the rule/price-definition classes just for the test
        $originalFilter = ['_name' => 'unknownPluginRule', 'operator' => '='];
        $priceDefinition = ['type' => 'percentage', 'percentage' => -20, 'filter' => $originalFilter];

        $connection = static::getContainer()->get(Connection::class);
        $lineItemId = $connection->fetchOne(
            'SELECT LOWER(HEX(id)) FROM order_line_item WHERE order_id = :id LIMIT 1',
            ['id' => Uuid::fromHexToBytes($orderId)]
        );
        static::assertIsString($lineItemId);

        $connection->update(
            'order_line_item',
            ['price_definition' => json_encode($priceDefinition, \JSON_THROW_ON_ERROR)],
            ['id' => Uuid::fromHexToBytes($lineItemId)]
        );

        // the order must still be readable (decode no longer throws)
        $order = $this->orderRepository->search(new Criteria([$orderId]), $this->context)->getEntities()->first();
        static::assertNotNull($order);

        // this is what the order detail page triggers on load; createVersionedOrder() asserts HTTP 200
        $versionId = $this->createVersionedOrder($orderId);

        // the unresolvable filter round-trips losslessly into the cloned version
        $stored = $connection->fetchOne(
            'SELECT price_definition FROM order_line_item WHERE id = :id AND version_id = :version',
            ['id' => Uuid::fromHexToBytes($lineItemId), 'version' => Uuid::fromHexToBytes($versionId)]
        );
        static::assertIsString($stored);
        $decoded = json_decode($stored, true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame($originalFilter, $decoded['filter']);
    }

    public function testRecalculateSucceedsForOrderWithUnregisteredRuleConditionInPromotionFilter(): void
    {
        // A promotion discount whose "consider" rule was contributed by a now-uninstalled plugin: the
        // filter inside the discount line item's price definition references a rule that is no longer
        // registered. Recalculating the order must complete (blogs keep their normal price definition).
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];
        $versionId = $this->createVersionedOrder($orderId);

        // attach a real automatic percentage promotion -> creates a genuine promotion discount line item
        $promotionId = $this->createPromotion(10.0, null, PromotionDiscountEntity::TYPE_PERCENTAGE);
        $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);

        // simulate the plugin being uninstalled: point the discount's price-definition filter at an
        // unregistered rule condition. Only the filter is touched; the rest of the discount is untouched.
        $connection = static::getContainer()->get(Connection::class);
        $row = $connection->fetchAssociative(
            'SELECT id, price_definition FROM order_line_item WHERE order_id = :oid AND version_id = :vid AND type = :type LIMIT 1',
            ['oid' => Uuid::fromHexToBytes($orderId), 'vid' => Uuid::fromHexToBytes($versionId), 'type' => PromotionProcessor::LINE_ITEM_TYPE]
        );
        static::assertIsArray($row);
        $priceDefinition = json_decode((string) $row['price_definition'], true, 512, \JSON_THROW_ON_ERROR);
        $priceDefinition['filter'] = ['_name' => 'unknownPluginRule', 'operator' => '='];
        $connection->update(
            'order_line_item',
            ['price_definition' => json_encode($priceDefinition, \JSON_THROW_ON_ERROR)],
            ['id' => $row['id'], 'version_id' => Uuid::fromHexToBytes($versionId)]
        );

        // recalculation must complete without throwing
        $versionContext = $this->context->createWithVersionId($versionId);
        $errors = static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        // dropping the discount must be surfaced as a warning instead of happening silently
        $unknownConditionErrors = $errors->filterInstance(PromotionDiscountUnknownConditionError::class);
        static::assertCount(1, $unknownConditionErrors);
        $unknownConditionError = $unknownConditionErrors->first();
        static::assertInstanceOf(PromotionDiscountUnknownConditionError::class, $unknownConditionError);
        static::assertSame('unknownPluginRule', $unknownConditionError->getOriginalConditionName());

        // order is still intact: blogs survive, and the now-unmatchable discount is dropped (fail-closed)
        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))->addAssociation('lineItems'),
            $versionContext
        )->getEntities()->first();
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertGreaterThan(0, $order->getLineItems()->filterByType(LineItem::BLOG_LINE_ITEM_TYPE)->count());
        static::assertCount(0, $order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE));
    }

    #[DataProvider('customLineItemProvider')]
    public function testAddCustomLineItemSdf(LineItem $lineItem, int $positionCount): void
    {
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];
        $versionId = $this->createVersionedOrder($orderId);
        $context = Context::createDefaultContext()->createWithVersionId($versionId);

        $this->getContainer()->get(RecalculationService::class)->addCustomLineItem($orderId, $lineItem, $context);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.positions');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->get($orderId);
        static::assertNotNull($order);

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);
        static::assertCount(3, $lineItems);

        $positions = $order->getDeliveries()?->first()?->getPositions();
        static::assertNotNull($positions);
        static::assertCount($positionCount, $positions);
    }

    public static function customLineItemProvider(): \Generator
    {
        yield 'line item type custom, shipping cost aware' => [
            (new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setLabel('Test custom line item')
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([new TaxRule(19)]))),
            3,
        ];

        yield 'line item type custom, not shipping cost aware' => [
            (new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE))
                ->setLabel('Test custom line item')
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([new TaxRule(19)])))
                ->setPayloadValue(LineItem::PAYLOAD_BLOG_TYPE, BlogDefinition::TYPE_DIGITAL),
            2,
        ];
    }

    public function testPersistOrderAndConvertToCart(): void
    {
        $parentBlogId = Uuid::randomHex();
        $childBlogId = Uuid::randomHex();
        // to test the sorting, the parentId has to be greater than the rootId
        $parentBlogId = substr_replace($parentBlogId, '0', 0, 1);
        $rootBlogId = substr_replace($parentBlogId, 'f', 0, 1);

        $cart = $this->generateDemoCart($parentBlogId, $rootBlogId);

        $cart = $this->addBlog($cart, $childBlogId);

        $blog1 = $cart->get($parentBlogId);
        $blog2 = $cart->get($childBlogId);

        static::assertNotNull($blog1);
        static::assertNotNull($blog2);

        $blog1->getChildren()->add($blog2);
        $cart->remove($childBlogId);

        $cart = static::getContainer()->get(Processor::class)
            ->process($cart, $this->channelContext, new CartBehavior());

        $orderId = $this->persistCart($cart)['orderId'];

        $deliveryCriteria = new Criteria();
        $deliveryCriteria->addAssociation('positions');

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod.tax')
            ->addAssociation('deliveries.shippingMethod.deliveryTime')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getNestedLineItems());

        // check lineItem sorting
        $idx = 0;
        foreach ($order->getNestedLineItems() as $lineItem) {
            if ($idx === 0) {
                static::assertSame($parentBlogId, $lineItem->getReferencedId());
            } else {
                static::assertSame($rootBlogId, $lineItem->getReferencedId());
            }
            ++$idx;
        }

        $convertedCart = static::getContainer()->get(OrderConverter::class)->convertToCart($order, $this->context);

        // check token
        static::assertNotSame($cart->getToken(), $convertedCart->getToken());
        static::assertTrue(Uuid::isValid($convertedCart->getToken()));

        // check lineItem sorting
        $idx = 0;
        foreach ($convertedCart->getLineItems() as $lineItem) {
            if ($idx === 0) {
                static::assertSame($parentBlogId, $lineItem->getId());
            } else {
                static::assertSame($rootBlogId, $lineItem->getId());
            }
            ++$idx;
        }
        // set token to be equal for further comparison
        $cart->setToken($convertedCart->getToken());

        $this->removeExtensions($cart);
        $this->removeExtensions($convertedCart);

        // remove delivery information from line items
        // the order stores the GARAN label each blog was placed with, none for these blogs

        foreach ($cart->getDeliveries() as $delivery) {
            // remove address from ShippingLocation
            $property = new \ReflectionProperty(ShippingLocation::class, 'address');
            $property->setValue($delivery->getLocation(), null);

            foreach ($delivery->getPositions() as $position) {
                $position->getLineItem()->setDeliveryInformation(null);
                $position->getLineItem()->setQuantityInformation(null);
                $position->getLineItem()->setPayloadValue('garanLabel', null);

                foreach ($position->getLineItem()->getChildren() as $lineItem) {
                    $lineItem->setDeliveryInformation(null);
                    $lineItem->setQuantityInformation(null);
                    $lineItem->setPayloadValue('garanLabel', null);
                }
            }

            $delivery->getShippingMethod()->setPrices(new ShippingMethodPriceCollection());
        }

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->setDeliveryInformation(null);
            $lineItem->setQuantityInformation(null);
            $lineItem->setPayloadValue('garanLabel', null);
        }

        $this->resetDataTimestamps($cart->getLineItems());
        foreach ($cart->getDeliveries() as $delivery) {
            $this->resetDataTimestamps($delivery->getPositions()->getLineItems());
        }
        $cart->setRuleIds([]);
        // The behaviour will be set during the process, therefore we remove it here
        $cart->setBehavior(null);

        // unique identifier is set at runtime to be random uuid
        foreach ($convertedCart->getLineItems()->getFlat() as $lineItem) {
            $lineItem->assign(['uniqueIdentifier' => 'foo']);
        }

        foreach ($convertedCart->getDeliveries() as $delivery) {
            foreach ($delivery->getPositions() as $position) {
                $position->getLineItem()->assign(['uniqueIdentifier' => 'foo']);

                foreach ($position->getLineItem()->getChildren()->getFlat() as $lineItem) {
                    $lineItem->assign(['uniqueIdentifier' => 'foo']);
                }
            }
        }

        $this->resetPayloadProtection($cart);
        $this->resetPayloadProtection($convertedCart);

        static::assertEquals($cart, $convertedCart);
    }

    public function testRecalculationController(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getOrderUser());

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testRecalculationControllerWithNonSystemLanguage(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart, $this->getDeDeLanguageId())['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertSame($this->getDeDeLanguageId(), $order->getLanguageId());
    }

    public function testRecalculateLiveVersionIsNotAllowed(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        $this->expectExceptionObject(OrderException::canNotRecalculateLiveVersion($orderId));

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $this->context);
    }

    public function testRecalculationWithDeletedUser(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        static::getContainer()->get('user.repository')->delete([['id' => $this->userId]], $this->context);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $versionContext)->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getOrderUser());

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddBlogToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $order = $this->persistCart($cart);
        $orderId = $order['orderId'];
        $oldTotal = $order['total'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $blogName = 'Test';
        $blogPrice = 10.0;
        $blogTaxRate = 19.0;
        $this->addBlogToVersionedOrder($blogName, $blogPrice, $blogTaxRate, $orderId, $versionId, $oldTotal);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first();
        static::assertNotNull($delivery);
        $newShippingCosts = $delivery->getShippingCosts();

        $firstTax = $newShippingCosts->getCalculatedTaxes()->first();
        $lastTax = $newShippingCosts->getCalculatedTaxes()->last();

        // tax is now mixed
        static::assertCount(2, $newShippingCosts->getCalculatedTaxes());
        static::assertNotNull($firstTax);
        static::assertSame(19.0, $firstTax->getTaxRate());
        static::assertNotNull($lastTax);
        static::assertSame(5.0, $lastTax->getTaxRate());
    }

    public function testRecalculationMovesTheOpenTransactionToTheNewTotal(): void
    {
        $order = $this->persistCart($this->generateDemoCart());
        $orderId = $order['orderId'];
        $transactionId = $this->fetchOrderWithTransactions($orderId, $this->context)->getPrimaryOrderTransactionId();

        $versionId = $this->createVersionedOrder($orderId);
        $this->addBlogToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, $order['total']);

        $versionedOrder = $this->fetchOrderWithTransactions($orderId, $this->context->createWithVersionId($versionId));
        static::assertNotNull($versionedOrder->getTransactions());
        static::assertCount(1, $versionedOrder->getTransactions());

        $transaction = $versionedOrder->getTransactions()->first();
        static::assertNotNull($transaction);
        static::assertSame($transactionId, $transaction->getId());
        static::assertSame(OrderTransactionStates::STATE_OPEN, $transaction->getStateMachineState()?->getTechnicalName());
        static::assertSame($versionedOrder->getAmountTotal(), $transaction->getAmount()->getTotalPrice());
        static::assertNotSame($order['total'], $transaction->getAmount()->getTotalPrice());
    }

    public function testRecalculationKeepsTheAmountOfAPaidTransaction(): void
    {
        $order = $this->persistCart($this->generateDemoCart());
        $orderId = $order['orderId'];

        $transactionId = $this->fetchOrderWithTransactions($orderId, $this->context)->getPrimaryOrderTransactionId();
        static::assertNotNull($transactionId);
        static::getContainer()->get(StateMachineRegistry::class)->transition(
            new Transition(OrderTransactionDefinition::ENTITY_NAME, $transactionId, StateMachineTransitionActions::ACTION_PAID, 'stateId'),
            $this->context
        );

        $versionId = $this->createVersionedOrder($orderId);
        $this->addBlogToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, $order['total']);

        $transaction = $this->fetchOrderWithTransactions($orderId, $this->context->createWithVersionId($versionId))->getTransactions()?->first();
        static::assertNotNull($transaction);
        static::assertSame(OrderTransactionStates::STATE_PAID, $transaction->getStateMachineState()?->getTechnicalName());
        static::assertSame($order['total'], $transaction->getAmount()->getTotalPrice());
    }

    #[DataProvider('liveTransactionTransitionProvider')]
    public function testMergeMovesTheTransactionOnlyWhileItStillAwaitsPayment(?string $liveTransition, bool $followsTheNewTotal): void
    {
        $order = $this->persistCart($this->generateDemoCart());
        $orderId = $order['orderId'];
        $transactionId = $this->fetchOrderWithTransactions($orderId, $this->context)->getPrimaryOrderTransactionId();
        static::assertNotNull($transactionId);

        $versionId = $this->createVersionedOrder($orderId);
        $this->addBlogToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, $order['total']);

        if ($liveTransition !== null) {
            static::getContainer()->get(StateMachineRegistry::class)->transition(
                new Transition(OrderTransactionDefinition::ENTITY_NAME, $transactionId, $liveTransition, 'stateId'),
                $this->context
            );
        }

        $this->orderRepository->merge($versionId, $this->context);

        $mergedOrder = $this->fetchOrderWithTransactions($orderId, $this->context);
        static::assertNotSame($order['total'], $mergedOrder->getAmountTotal());

        $transaction = $mergedOrder->getTransactions()?->get($transactionId);
        static::assertNotNull($transaction);
        static::assertSame(
            $followsTheNewTotal ? $mergedOrder->getAmountTotal() : $order['total'],
            $transaction->getAmount()->getTotalPrice()
        );
    }

    public static function liveTransactionTransitionProvider(): \Generator
    {
        yield 'still open' => [null, true];
        yield 'partially paid during the edit' => [StateMachineTransitionActions::ACTION_PAID_PARTIALLY, false];
        yield 'paid during the edit' => [StateMachineTransitionActions::ACTION_PAID, false];
    }

    public function testAddBlogToOrderWithUserComment(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $cart->setUserComment('test comment');
        $cart->setAffiliateCode('test_affiliate_code');
        $cart->setCampaignCode('test_campaign_code');
        $order = $this->persistCart($cart);

        $orderId = $order['orderId'];
        $oldTotal = $order['total'];
        $oldOrderStateId = $order['stateId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $blogName = 'Test';
        $blogPrice = 10.0;
        $blogTaxRate = 19.0;

        $blogId = $this->createBlog($blogName, $blogPrice, $blogTaxRate);

        // add blog to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/blog/%s',
                $orderId,
                $blogId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame('test comment', $order->getUserComment());
        static::assertSame('test_affiliate_code', $order->getAffiliateCode());
        static::assertSame('test_campaign_code', $order->getCampaignCode());
        static::assertSame($oldOrderStateId, $order->getStateId());

        $blog = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $blogId) {
                $blog = $lineItem;
            }
        }

        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrice());
        $blogPriceInclTax = 10 + ($blogPrice * $blogTaxRate / 100);
        static::assertSame($blog->getPrice()->getUnitPrice(), $blogPriceInclTax);
        $taxRule = $blog->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), $blogTaxRate);

        static::assertNotNull($order->getAmountTotal());
        static::assertSame($oldTotal + $blogPriceInclTax, $order->getAmountTotal());
    }

    public function testAddBlogToOrderTriggersStockUpdate(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $order = $this->persistCart($cart);
        $orderId = $order['orderId'];
        $oldTotal = $order['total'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $blogName = 'Test';
        $blogPrice = 10.0;
        $blogTaxRate = 19.0;
        $blogId = $this->addBlogToVersionedOrder($blogName, $blogPrice, $blogTaxRate, $orderId, $versionId, $oldTotal);

        $this->orderRepository
            ->merge($versionId, Context::createDefaultContext());

        $stocks = static::getContainer()->get(Connection::class)
            ->fetchAssociative('SELECT stock, available_stock FROM blog WHERE id = :id', ['id' => Uuid::fromHexToBytes($blogId)]);

        static::assertIsArray($stocks);

        static::assertSame(4, (int) $stocks['stock']);
        static::assertSame(4, (int) $stocks['available_stock']);
    }

    public function testAddCustomLineItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'total' => $oldTotal, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->addCustomLineItemToVersionedOrder($orderId, $versionId, $oldTotal, $orderDateTime, $stateId);
    }

    public function testAddCreditItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'total' => $total, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->addCreditItemToVersionedOrder($orderId, $versionId, $total, $orderDateTime, $stateId);
    }

    public function testAddPromotionItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create a promotion code with discount
        $code = 'GET5';
        $discountValue = 5.0;
        $this->createPromotion($discountValue, $code);

        $this->addPromotionItemToVersionedOrder($orderId, $versionId, $code, $orderDateTime, $stateId);
    }

    public function testAddNonExistingPromotionItemToOrder(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/promotion-item', $orderId),
            ['code' => 'some-random-code'],
            ['HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame($errors[0]['translatedMessage'], 'Promo code "some-random-code" could not be found.');
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function testToggleAutomaticPromotions(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create an automatic promotion with discount
        $discountValue = 5.0;
        $promotionId = $this->createPromotion($discountValue);

        $this->toggleAutomaticPromotions($orderId, $versionId, $promotionId, $orderDateTime, $stateId);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed
     */
    public function testToggleAutomaticPromotionsForDelivery(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        // create order
        $cart = $this->generateDemoCart();

        $shippingMethod = $this->shippingMethodRepository->search(new Criteria(), $this->context)->getEntities()->first();
        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);

        $cart->setDeliveries(new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(new \DateTime(), new \DateTime()),
                $shippingMethod,
                new ShippingLocation(new CountryEntity(), null, $this->getUserAddress(Uuid::randomHex())),
                new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection())
            ),
        ]));

        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->createShippingDiscount(100);

        $this->toggleAutomaticPromotionsForDelivery($orderId, $versionId, $orderDateTime);
    }

    public function testApplyAutomaticPromotions(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create an automatic promotion with discount
        $discountValue = 5.0;
        $promotionId = $this->createPromotion($discountValue);

        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);
        $promotionItem = $order->getLineItems()?->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first();

        static::assertCount(1, $content['errors']);
        static::assertNotNull($promotionItem);
        static::assertSame('Discount "auto promotion" has been added', array_values($content['errors'])[0]['translatedMessage']);
        static::assertSame($order->getStateId(), $stateId);

        // On recalculation, promotion is applied once more, creating a new line item.
        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);
        $newPromotionItem = $order->getLineItems()?->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first();

        static::assertCount(0, $content['errors']);
        static::assertNotNull($newPromotionItem);
        static::assertSame($promotionItem->getId(), $newPromotionItem->getId(), 'line-item id of promotion should not differ between recalculations');
        static::assertSame($promotionItem->getPayload(), $newPromotionItem->getPayload());
    }

    public function testApplyAutomaticShippingPromotions(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'stateId' => $stateId] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create an automatic promotion with discount
        $promotionId = $this->createShippingDiscount(5.0);

        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);

        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame('Discount "delivery promotion" has been added', $errors[0]['translatedMessage']);
        static::assertSame($order->getStateId(), $stateId);

        static::assertNotNull($order->getDeliveries());
        $deliveryIds = $order->getDeliveries()->getKeys();
        static::assertCount(2, $deliveryIds);
        $promotionDelivery = $order->getDeliveries()->get($deliveryIds[1]);
        static::assertSame(-0.5, $promotionDelivery?->getShippingCosts()->getTotalPrice());

        // On recalculation, promotion is applied once more, creating a new delivery.
        // The old one is expected to be deleted.
        [$order, $content] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);
        static::assertCount(0, $content['errors']);
        static::assertNotNull($order->getDeliveries());
        $deliveryIds = $order->getDeliveries()->getKeys();
        static::assertCount(2, $deliveryIds);
        $newPromotionDelivery = $order->getDeliveries()->get($deliveryIds[1]);
        static::assertSame(-0.5, $newPromotionDelivery?->getShippingCosts()->getTotalPrice());

        static::assertNotSame($newPromotionDelivery->getId(), $promotionDelivery->getId());
    }

    public function testEditOrderWithFreeShippingPromotionKeepsDeliveryPositions(): void
    {
        // checkout an order with a 100% shipping discount promotion (shipping delivery + discount delivery)
        $this->createShippingDiscount(100, 'FREESHIP');
        $cart = $this->generateDemoCart();
        $cart->add((new PromotionLineItemFactory())->create(['referencedId' => 'FREESHIP'], $this->channelContext));
        $cart = static::getContainer()->get(Processor::class)->process($cart, $this->channelContext, new CartBehavior());
        $orderId = $this->persistCart($cart)['orderId'];

        // admin edit: add a blog and save (recalculate)
        $versionContext = $this->context->createWithVersionId($this->createVersionedOrder($orderId));
        $blogId = $this->createBlog('new blog', 10.0, 19.0);
        static::getContainer()->get(RecalculationService::class)->addBlogToOrder($orderId, $blogId, 1, $versionContext);

        $order = $this->orderRepository->search(
            (new Criteria([$orderId]))->addAssociation('deliveries.positions'),
            $versionContext
        )->getEntities()->first();
        static::assertNotNull($order);
        $deliveries = $order->getDeliveries();
        static::assertNotNull($deliveries);
        static::assertCount(2, $deliveries);

        $shipping = $deliveries->filter(static fn (OrderDeliveryEntity $delivery) => $delivery->getShippingCosts()->getTotalPrice() >= 0)->first();
        $discount = $deliveries->filter(static fn (OrderDeliveryEntity $delivery) => $delivery->getShippingCosts()->getTotalPrice() < 0)->first();
        static::assertNotNull($shipping);
        static::assertNotNull($discount);

        // both initial blogs plus the newly added one keep a shipping position; the discount delivery has none
        static::assertCount(3, $shipping->getPositions() ?? []);
        static::assertCount(0, $discount->getPositions() ?? []);
    }

    public function testRecalculationOfPinnedDisabledPromotion(): void
    {
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        $promotionId = $this->createPromotion(10.0, 'GET5', PromotionDiscountEntity::TYPE_PERCENTAGE);

        $versionId = $this->createVersionedOrder($orderId);
        $order = $this->addPromotionItemToVersionedOrder($orderId, $versionId, 'GET5', $orderDateTime, $stateId);

        static::assertSame(225.98, $order->getAmountTotal());

        static::getContainer()->get('promotion.repository')->upsert(
            [['id' => $promotionId, 'active' => false]],
            $this->context,
        );

        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            server: [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertNotNull($order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertSame(225.98, $order->getAmountTotal());
    }

    public function testRecalculationOfPinnedPromotionWithBlogAdded(): void
    {
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'orderDateTime' => $orderDateTime, 'stateId' => $stateId] = $this->persistCart($cart);

        $promotionId = $this->createPromotion(10.0, 'GET5', PromotionDiscountEntity::TYPE_PERCENTAGE);

        $versionId = $this->createVersionedOrder($orderId);
        $order = $this->addPromotionItemToVersionedOrder($orderId, $versionId, 'GET5', $orderDateTime, $stateId);

        static::assertSame(225.98, $order->getAmountTotal());

        static::getContainer()->get('promotion.repository')->upsert(
            [['id' => $promotionId, 'active' => false]],
            $this->context,
        );

        $this->addBlogToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, 224.79);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getLineItems());
        static::assertCount(4, $order->getLineItems());
        static::assertNotNull($order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertNotSame(237.17, $order->getAmountTotal(), 'Promotion of order isn\'t recalculated');
        static::assertSame(236.69, $order->getAmountTotal());
    }

    public function testRecalculationOfPinnedAutomaticDisabledPromotion(): void
    {
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId] = $this->persistCart($cart);
        $versionId = $this->createVersionedOrder($orderId);

        $promotionId = $this->createPromotion(10.0, null, PromotionDiscountEntity::TYPE_PERCENTAGE);

        [$order] = $this->applyAutomaticPromotions($orderId, $versionId, $promotionId);

        static::assertSame(225.98, $order->getAmountTotal());

        static::getContainer()->get('promotion.repository')->upsert(
            [['id' => $promotionId, 'active' => false]],
            $this->context,
        );

        $this->addBlogToVersionedOrder('Test', 10.0, 19.0, $orderId, $versionId, 224.79);

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertNotNull($order->getLineItems());
        static::assertCount(4, $order->getLineItems());
        static::assertNotNull($order->getLineItems()->filterByType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertSame(236.69, $order->getAmountTotal());

        // as promotion is disabled, it should be removed again
        [$order] = $this->applyAutomaticPromotions($orderId, $versionId, null);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertSame(261.88, $order->getAmountTotal());
    }

    public function testCreatedVersionedOrderAndMerge(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        ['orderId' => $orderId, 'total' => $oldTotal, 'orderDateTime' => $orderDateTime] = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $blogName = 'Test';
        $blogPrice = 10.0;
        $blogTaxRate = 19.0;
        $blogId = $this->addBlogToVersionedOrder(
            $blogName,
            $blogPrice,
            $blogTaxRate,
            $orderId,
            $versionId,
            $oldTotal
        );

        // merge versioned order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/version/merge/%s/%s',
                static::getContainer()->get(OrderDefinition::class)->getEntityName(),
                $versionId
            )
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read merged order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());

        $blog = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $blogId) {
                $blog = $lineItem;
            }
        }

        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrice());
        $blogPriceInclTax = 10 + ($blogPrice * $blogTaxRate / 100);
        static::assertSame($blog->getPrice()->getUnitPrice(), $blogPriceInclTax);
        $taxRule = $blog->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), $blogTaxRate);
        static::assertNotNull($order->getOrderDateTime());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testChangeShippingCosts(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $deliveries = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities();

        static::assertCount(1, $deliveries);

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $shippingCosts = $delivery->getShippingCosts();

        static::assertSame(1, $shippingCosts->getQuantity());
        static::assertSame(10.0, $shippingCosts->getUnitPrice());
        static::assertSame(10.0, $shippingCosts->getTotalPrice());
        static::assertCount(2, $shippingCosts->getCalculatedTaxes());

        // change shipping costs
        $newShippingCosts = new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection());

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);

        $payload = [
            'id' => $delivery->getId(),
            'shippingCosts' => $newShippingCosts,
        ];

        $this->orderDeliveryRepository->upsert([$payload], $versionContext);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $deliveries = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities();

        $delivery = $deliveries->first();
        static::assertNotNull($delivery);
        $newShippingCosts = $delivery->getShippingCosts();

        static::assertSame(1, $newShippingCosts->getQuantity());
        static::assertSame(5.0, $newShippingCosts->getUnitPrice());
        static::assertSame(5.0, $newShippingCosts->getTotalPrice());

        $firstTax = $newShippingCosts->getCalculatedTaxes()->first();
        $lastTax = $newShippingCosts->getCalculatedTaxes()->last();

        // tax is now mixed
        static::assertCount(2, $newShippingCosts->getCalculatedTaxes());
        static::assertNotNull($firstTax);
        static::assertSame(19.0, $firstTax->getTaxRate());
        static::assertNotNull($lastTax);
        static::assertSame(5.0, $lastTax->getTaxRate());
    }

    public function testDeleteLineItemsAfterRecalculateOrderWitchInactiveBlogs(): void
    {
        // Arrange
        $inactiveBlogId = Uuid::randomHex();

        $cart = $this->generateDemoCart($inactiveBlogId);
        $orderId = $this->persistCart($cart)['orderId'];

        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItemWithInactiveBlog = $order->getLineItems()?->filter(
            static fn (OrderLineItemEntity $lineItem) => $lineItem->getIdentifier() === $inactiveBlogId
        )->first();

        static::assertNotNull($lineItemWithInactiveBlog);

        static::getContainer()->get('blog.repository')->update([['id' => $inactiveBlogId, 'active' => false]], $this->context);

        $options = [
            ChannelContextService::PERMISSIONS => [
                ...OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
                BlogCartProcessor::KEEP_INACTIVE_BLOG => false,
            ],
        ];

        // Act
        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext, $options);

        // Assert
        $order = $this->orderRepository->search($criteria, $versionContext)->getEntities()->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        $lineItemWithInactiveBlog = $order->getLineItems()?->filter(
            static fn (OrderLineItemEntity $lineItem) => $lineItem->getIdentifier() === $inactiveBlogId
        )->first();

        static::assertNull($lineItemWithInactiveBlog);
    }

    public function testRecalculateOrderWithInactiveBlog(): void
    {
        $inactiveBlogId = Uuid::randomHex();
        // create order
        $cart = $this->generateDemoCart($inactiveBlogId);
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->get($orderId);
        static::assertNotNull($order);

        static::assertSame(224.07, $order->getPrice()->getNetPrice());
        static::assertSame(249.98, $order->getPrice()->getTotalPrice());
        static::assertSame(239.98, $order->getPrice()->getPositionPrice());

        static::getContainer()->get('blog.repository')->update([['id' => $inactiveBlogId, 'active' => false]], $this->context);

        static::getContainer()->get(RecalculationService::class)->recalculate($orderId, $versionContext);

        $order = $this->orderRepository->search($criteria, $this->context)->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getPrice());

        static::assertSame(224.07, $order->getPrice()->getNetPrice());
        static::assertSame(249.98, $order->getPrice()->getTotalPrice());
        static::assertSame(239.98, $order->getPrice()->getPositionPrice());
    }

    public function testForeachLoopInCalculateDeliveryFunction(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->addSecondPriceRuleToShippingMethod($priceRuleId, $shippingMethodId);
        $this->channelContext->setRuleIds(array_merge($this->channelContext->getRuleIds(), [$priceRuleId]));

        $prop = new \ReflectionProperty(ChannelContext::class, 'shippingMethod');
        $prop->setValue($this->channelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $shippingCosts = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first()?->getShippingCosts();
        static::assertNotNull($shippingCosts);

        static::assertSame(1, $shippingCosts->getQuantity());
        static::assertSame(15.0, $shippingCosts->getUnitPrice());
        static::assertSame(15.0, $shippingCosts->getTotalPrice());
    }

    public function testStartAndEndConditionsInPriceRule(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->addSecondShippingMethodPriceRule($priceRuleId, $shippingMethodId);
        $this->channelContext->setRuleIds(array_merge($this->channelContext->getRuleIds(), [$priceRuleId]));

        $prop = new \ReflectionProperty(ChannelContext::class, 'shippingMethod');
        $prop->setValue($this->channelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->getAssociation('shippingMethod')->addAssociation('prices');

        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $shippingMethod = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first()?->getShippingMethod();
        static::assertNotNull($shippingMethod);

        $firstPriceRule = $shippingMethod->getPrices()->first();
        static::assertNotNull($firstPriceRule);
        $secondPriceRule = $shippingMethod->getPrices()->last();
        static::assertNotNull($secondPriceRule);

        static::assertSame($firstPriceRule->getRuleId(), $secondPriceRule->getRuleId());
        static::assertGreaterThan($firstPriceRule->getQuantityStart(), $firstPriceRule->getQuantityEnd());
        static::assertGreaterThan($firstPriceRule->getQuantityEnd(), $secondPriceRule->getQuantityStart());
        static::assertGreaterThan($secondPriceRule->getQuantityStart(), $secondPriceRule->getQuantityEnd());
    }

    public function testIfCorrectConditionIsUsedCalculationByLineItemCount(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->addSecondShippingMethodPriceRule($priceRuleId, $shippingMethodId);
        $this->channelContext->setRuleIds(array_merge($this->channelContext->getRuleIds(), [$priceRuleId]));

        $prop = new \ReflectionProperty(ChannelContext::class, 'shippingMethod');
        $prop->setValue($this->channelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));

        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first();
        static::assertNotNull($delivery);
        static::assertSame(1, $delivery->getShippingCosts()->getQuantity());
        static::assertSame(15.0, $delivery->getShippingCosts()->getUnitPrice());
        static::assertSame(15.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testIfCorrectConditionIsUsedPriceCalculation(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->createTwoConditionsWithDifferentQuantities($priceRuleId, $shippingMethodId, DeliveryCalculator::CALCULATION_BY_PRICE);
        $this->channelContext->setRuleIds(array_merge($this->channelContext->getRuleIds(), [$priceRuleId]));

        $prop = new \ReflectionProperty(ChannelContext::class, 'shippingMethod');
        $prop->setValue($this->channelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first();
        static::assertNotNull($delivery);
        static::assertSame(1, $delivery->getShippingCosts()->getQuantity());
        static::assertSame(9.99, $delivery->getShippingCosts()->getUnitPrice());
        static::assertSame(9.99, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testIfCorrectConditionIsUsedWeightCalculation(): void
    {
        $priceRuleId = Uuid::randomHex();
        $shippingMethodId = Uuid::randomHex();
        $shippingMethod = $this->createTwoConditionsWithDifferentQuantities($priceRuleId, $shippingMethodId, DeliveryCalculator::CALCULATION_BY_WEIGHT);
        $this->channelContext->setRuleIds(array_merge($this->channelContext->getRuleIds(), [$priceRuleId]));

        $prop = new \ReflectionProperty(ChannelContext::class, 'shippingMethod');
        $prop->setValue($this->channelContext, $shippingMethod);

        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $delivery = $this->orderDeliveryRepository->search($criteria, $versionContext)->getEntities()->first();
        static::assertNotNull($delivery);
        static::assertSame(1, $delivery->getShippingCosts()->getQuantity());
        static::assertSame(15.0, $delivery->getShippingCosts()->getUnitPrice());
        static::assertSame(15.0, $delivery->getShippingCosts()->getTotalPrice());
    }

    public function testReplaceBillingAddress(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart)['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create a new address for the existing user

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getAddresses());

        $orderAddressId = $order->getAddresses()->first()?->getId();
        static::assertIsString($orderAddressId);

        $firstName = 'Replace first name';
        $lastName = 'Replace last name';
        $street = 'Replace street';
        $city = 'Replace city';
        $zipcode = '98765';

        $userAddressId = $this->addAddressToUser(
            $this->userId,
            $firstName,
            $lastName,
            $street,
            $city,
            $zipcode
        );

        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order-address/%s/user-address/%s',
                $orderAddressId,
                $userAddressId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('addresses');

        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getAddresses());
        $orderAddress = $order->getAddresses()->first();
        static::assertNotNull($orderAddress);

        static::assertSame($orderAddressId, $orderAddress->getId());
        static::assertSame($firstName, $orderAddress->getFirstName());
        static::assertSame($lastName, $orderAddress->getLastName());
        static::assertSame($street, $orderAddress->getStreet());
        static::assertSame($city, $orderAddress->getCity());
        static::assertSame($zipcode, $orderAddress->getZipcode());
    }

    public function testRecalculationControllerWithEmptyLineItems(): void
    {
        // create order
        $cart = $this->generateDemoCart();
        $order = $this->persistCart($cart);

        $orderId = $order['orderId'];

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        // recalculate order
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');

        $order = $this->orderRepository->search($criteria, $versionContext)->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame($order->getLineItems()->count(), 2);

        // delete all line items
        $ids = $order->getLineItems()->fmap(static fn (OrderLineItemEntity $lineItem) => ['id' => $lineItem->getId()]);
        static::getContainer()->get('order_line_item.repository')->delete(array_values($ids), $versionContext);

        $order = $this->orderRepository->search($criteria, $versionContext)->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame($order->getLineItems()->count(), 0);

        // recalculate order 2nd time
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    protected function getValidCountryIdWithTaxes(): string
    {
        $countryId = $this->getValidCountryId();

        $data = [
            'id' => $countryId,
            'iso' => 'XX',
            'iso3' => 'XXX',
            'active' => true,
            'shippingAvailable' => true,
            'taxFree' => false,
            'position' => 10,
            'displayStateInRegistration' => false,
            'forceStateInRegistration' => false,
            'translations' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => 'Takatuka',
                ],
            ],
        ];

        static::getContainer()->get('country.repository')->upsert(
            [$data],
            $this->context
        );

        return $countryId;
    }

    private function resetPayloadProtection(Cart $cart): void
    {
        // remove delivery information from line items
        $payloadProtection = new \ReflectionProperty(LineItem::class, 'payloadProtection');

        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            $payloadProtection->setValue($lineItem, []);
        }

        foreach ($cart->getDeliveries() as $delivery) {
            foreach ($delivery->getPositions() as $position) {
                $payloadProtection->setValue($position->getLineItem(), []);
                foreach ($position->getLineItem()->getChildren() as $lineItem) {
                    $payloadProtection->setValue($lineItem, []);
                }
            }
        }
    }

    private function resetDataTimestamps(LineItemCollection $items): void
    {
        foreach ($items as $item) {
            $item->setDataTimestamp(null);
            $item->setDataContextHash(null);
            $this->resetDataTimestamps($item->getChildren());
        }
    }

    private function removeExtensions(Cart $cart): void
    {
        $this->removeLineItemsExtension($cart->getLineItems());

        foreach ($cart->getDeliveries() as $delivery) {
            $delivery->setExtensions([]);

            $delivery->getShippingMethod()->setExtensions([]);

            foreach ($delivery->getPositions() as $position) {
                $position->setExtensions([]);
                $this->removeLineItemsExtension(new LineItemCollection([$position->getLineItem()]));
            }
        }

        foreach ($cart->getTransactions() as $transaction) {
            $transaction->setExtensions([]);
        }

        $cart->setExtensions([]);
        $cart->setData(null);
    }

    private function removeLineItemsExtension(LineItemCollection $lineItems): void
    {
        foreach ($lineItems as $lineItem) {
            $lineItem->setExtensions([]);
            $this->removeLineItemsExtension($lineItem->getChildren());
        }
    }

    private function addAddressToUser(
        string $userId,
        string $firstName,
        string $lastName,
        string $street,
        string $city,
        string $zipcode
    ): string {
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $street,
                    'zipcode' => $zipcode,
                    'city' => $city,
                ],
            ],
        ];

        static::getContainer()->get('user.repository')->upsert([$user], $this->context);

        return $addressId;
    }

    private function createBlog(string $name, float $price, float $taxRate): string
    {
        $blogId = Uuid::randomHex();

        $blogNumber = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => $blogNumber,
            'stock' => 5,
            'name' => $name,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price + ($price * $taxRate / 100), 'net' => $price, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => $taxRate],
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$data], $this->context);

        return $blogId;
    }

    private function createPromotion(float $discountValue, ?string $code = null, string $type = PromotionDiscountEntity::TYPE_ABSOLUTE): string
    {
        $promotionId = Uuid::randomHex();

        $data = [
            'id' => $promotionId,
            'name' => 'auto promotion',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => false,
            'channels' => [
                ['channelId' => TestDefaults::CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => $type,
                    'value' => $discountValue,
                    'considerAdvancedRules' => false,
                ],
            ],
        ];

        if ($code) {
            $data['name'] = $code;
            $data['useCodes'] = true;
            $data['code'] = $code;
        }

        static::getContainer()->get('promotion.repository')->create([$data], $this->context);

        return $promotionId;
    }

    private function createShippingDiscount(float $discountValue, ?string $code = null): string
    {
        $promotionId = Uuid::randomHex();

        $data = [
            'id' => $promotionId,
            'name' => 'delivery promotion',
            'active' => true,
            'useCodes' => false,
            'useSetGroups' => false,
            'channels' => [
                ['channelId' => TestDefaults::CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_DELIVERY,
                    'type' => PromotionDiscountEntity::TYPE_PERCENTAGE,
                    'value' => $discountValue,
                    'considerAdvancedRules' => false,
                ],
            ],
        ];

        if ($code) {
            $data['name'] = $code;
            $data['useCodes'] = true;
            $data['code'] = $code;
        }

        static::getContainer()->get('promotion.repository')->create([$data], $this->context);

        return $promotionId;
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryIdWithTaxes(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('user.repository')->upsert([$user], $this->context);

        return $userId;
    }

    private function getUserAddress(string $id): UserAddressEntity
    {
        $address = new UserAddressEntity();
        $address->setId($id);
        $address->setCountryId($this->getValidCountryId());
        $address->setFirstName('Max');
        $address->setLastName('Mustermann');
        $address->setStreet('Musterstraße 1');
        $address->setZipcode('12345');
        $address->setCity('Musterstadt');

        return $address;
    }

    private function generateDemoCart(?string $blogId1 = null, ?string $blogId2 = null): Cart
    {
        $cart = new Cart(Uuid::randomHex());

        $cart = $this->addBlog($cart, $blogId1 ?? Uuid::randomHex());

        $cart = $this->addBlog($cart, $blogId2 ?? Uuid::randomHex(), [
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 5, 'name' => 'test'],
        ]);

        return $cart;
    }

    /**
     * @param array<string, array<string, int|string>|string> $options
     */
    private function addBlog(Cart $cart, string $id, array $options = []): Cart
    {
        $default = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'name' => 'test',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        $blog = array_replace_recursive($default, $options);

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $this->addTaxDataToChannel($this->channelContext, $blog['tax']);

        $lineItem = static::getContainer()->get(BlogLineItemFactory::class)
            ->create(['id' => $id, 'referencedId' => $id], $this->channelContext);
        $lineItem->markUnmodified();

        $lineItem->assign(['uniqueIdentifier' => 'foo']);

        $cart->add($lineItem);

        $cart = static::getContainer()->get(Processor::class)
            ->process($cart, $this->channelContext, new CartBehavior());

        return $cart;
    }

    /**
     * @return array{orderId: string, total: float, orderDateTime: \DateTimeInterface, stateId: string}
     */
    private function persistCart(Cart $cart, ?string $languageId = null): array
    {
        if ($languageId !== null) {
            $context = $this->channelContext->getContext();
            $context->assign([
                'languageIdChain' => array_merge([$languageId], $context->getLanguageIdChain()),
            ]);
        }
        $orderId = static::getContainer()->get(OrderPersister::class)->persist($cart, $this->channelContext);

        $criteria = new Criteria([$orderId]);
        $order = $this->orderRepository->search($criteria, $this->channelContext->getContext())->getEntities()->get($orderId);
        static::assertNotNull($order);

        return [
            'orderId' => $orderId,
            'total' => $order->getPrice()->getTotalPrice(),
            'orderDateTime' => $order->getOrderDateTime(),
            'stateId' => $order->getStateId(),
        ];
    }

    private function fetchOrderWithTransactions(string $orderId, Context $context): OrderEntity
    {
        $criteria = (new Criteria([$orderId]))->addAssociation('transactions.stateMachineState');

        $order = $this->orderRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($order);

        return $order;
    }

    private function createVersionedOrder(string $orderId): string
    {
        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/version/order/%s', $orderId)
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $versionId = $content['versionId'];
        static::assertSame($orderId, $content['id']);
        static::assertSame('order', $content['entity']);
        static::assertTrue(Uuid::isValid($versionId));

        return $versionId;
    }

    private function addBlogToVersionedOrder(
        string $blogName,
        float $blogPrice,
        float $blogTaxRate,
        string $orderId,
        string $versionId,
        float $oldTotal
    ): string {
        $blogId = $this->createBlog($blogName, $blogPrice, $blogTaxRate);

        // add blog to order
        $this->getBrowser()->request(
            'POST',
            \sprintf(
                '/api/_action/order/%s/blog/%s',
                $orderId,
                $blogId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/order/%s/recalculate', $orderId),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());

        $blog = $order->getLineItems()->firstWhere(
            static fn (OrderLineItemEntity $item) => $item->getIdentifier() === $blogId,
        );

        static::assertNotNull($blog);
        static::assertNotNull($blog->getPrice());
        $blogPriceInclTax = 10 + ($blogPrice * $blogTaxRate / 100);
        static::assertSame($blog->getPrice()->getUnitPrice(), $blogPriceInclTax);
        $taxRule = $blog->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), $blogTaxRate);

        static::assertSame($oldTotal + $blogPriceInclTax, $order->getAmountTotal());

        return $blogId;
    }

    private function addCustomLineItemToVersionedOrder(string $orderId, string $versionId, float $oldTotal, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $identifier = Uuid::randomHex();
        $data = [
            'identifier' => $identifier,
            'type' => LineItem::CUSTOM_LINE_ITEM_TYPE,
            'quantity' => 10,
            'label' => 'example label',
            'description' => 'example description',
            'priceDefinition' => [
                'price' => 27.99,
                'quantity' => 10,
                'isCalculated' => false,
                'precision' => 2,
                'taxRules' => [
                    [
                        'taxRate' => 19,
                        'percentage' => 100,
                    ],
                ],
            ],
        ];

        // add blog to order
        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/lineItem', $orderId),
            $data,
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $this->orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());

        $customLineItem = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $identifier) {
                $customLineItem = $lineItem;
            }
        }

        static::assertNotNull($customLineItem);
        static::assertNotNull($customLineItem->getPrice());
        static::assertSame($customLineItem->getPrice()->getUnitPrice(), 33.31);
        static::assertSame($customLineItem->getPrice()->getQuantity(), 10);
        static::assertSame($customLineItem->getPrice()->getTotalPrice(), 333.1);
        $taxRule = $customLineItem->getPrice()->getTaxRules()->first();
        static::assertNotNull($taxRule);
        static::assertSame($taxRule->getTaxRate(), 19.0);
        static::assertSame($taxRule->getPercentage(), 100.0);
        $calculatedTaxes = $customLineItem->getPrice()->getCalculatedTaxes()->first();
        static::assertNotNull($calculatedTaxes);
        static::assertSame($calculatedTaxes->getPrice(), 333.1);
        static::assertSame($calculatedTaxes->getTaxRate(), 19.0);
        static::assertSame($calculatedTaxes->getTax(), 53.18);

        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($customLineItem->getPrice()->getTotalPrice() + $oldTotal, $order->getAmountTotal());
        static::assertSame($stateId, $order->getStateId());
    }

    private function addCreditItemToVersionedOrder(string $orderId, string $versionId, float $oldTotal, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->orderRepository;

        $identifier = Uuid::randomHex();
        $creditAmount = -10.0;
        $data = [
            'identifier' => $identifier,
            'type' => LineItem::CREDIT_LINE_ITEM_TYPE,
            'quantity' => 1,
            'label' => 'awesome credit',
            'description' => 'schubbidu',
            'priceDefinition' => [
                'price' => $creditAmount,
                'quantity' => 1,
                'isCalculated' => false,
                'precision' => 2,
            ],
        ];

        // add credit item to order
        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/creditItem', $orderId),
            $data,
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertSame($oldTotal + $creditAmount, $order->getAmountTotal());

        $creditItem = $order->getLineItems()->filterByProperty('identifier', $identifier)->first();
        static::assertNotNull($creditItem);
        $price = $creditItem->getPrice();
        static::assertNotNull($price);

        static::assertSame($creditAmount, $price->getTotalPrice());
        $taxRules = $price->getCalculatedTaxes();
        static::assertCount(2, $taxRules);
        static::assertArrayHasKey(19, $taxRules->getElements());
        static::assertArrayHasKey(5, $taxRules->getElements());
        $tax19 = $taxRules->getElements()[19];
        static::assertSame(19.0, $tax19->getTaxRate());
        $tax5 = $taxRules->getElements()[5];
        static::assertSame(5.0, $tax5->getTaxRate());

        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($creditAmount, $tax19->getPrice() + $tax5->getPrice());
        static::assertSame($stateId, $order->getStateId());
    }

    private function addPromotionItemToVersionedOrder(string $orderId, string $versionId, string $code, \DateTimeInterface $orderDateTime, string $stateId): OrderEntity
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'code' => $code,
        ];

        // add promotion item to order
        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/promotion-item', $orderId),
            $data,
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $promotionItem = $order->getLineItems()->filterByProperty('referencedId', $code)->first();

        static::assertNotNull($promotionItem);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame($errors[0]['translatedMessage'], 'Discount "GET5" has been added');
        static::assertSame($stateId, $order->getStateId());

        return $order;
    }

    /**
     * @return array{0: OrderEntity, 1: array<mixed>}
     */
    private function applyAutomaticPromotions(string $orderId, string $versionId, ?string $promotionId): array
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/applyAutomaticPromotions', $orderId),
            $data,
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());

        $promotionItem = $order->getLineItems()->filterByType('promotion')->first();
        if ($promotionId) {
            static::assertNotNull($promotionItem);
            static::assertNotNull($promotionItem->getPayload());
            static::assertSame($promotionItem->getPayload()['promotionId'], $promotionId);
        } else {
            static::assertNull($promotionItem);
        }

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return [$order, $content];
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    private function toggleAutomaticPromotions(string $orderId, string $versionId, string $promotionId, \DateTimeInterface $orderDateTime, string $stateId): void
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/toggleAutomaticPromotions', $orderId),
            $data,
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getLineItems());
        static::assertCount(3, $order->getLineItems());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $promotionItem = $order->getLineItems()->filterByProperty('type', 'promotion')->first();

        static::assertNotNull($promotionItem);
        $payload = $promotionItem->getPayload();
        static::assertNotNull($payload);

        static::assertSame($payload['promotionId'], $promotionId);

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $content['errors']);

        $errors = array_values($content['errors']);
        static::assertSame($errors[0]['translatedMessage'], 'Discount "auto promotion" has been added');
        static::assertSame($stateId, $order->getStateId());
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    private function toggleAutomaticPromotionsForDelivery(string $orderId, string $versionId, \DateTimeInterface $orderDateTime): void
    {
        $orderRepository = $this->orderRepository;

        $data = [
            'skipAutomaticPromotions' => false,
        ];

        // add promotion item to order
        $this->getBrowser()->jsonRequest(
            'POST',
            \sprintf('/api/_action/order/%s/toggleAutomaticPromotions', $orderId),
            $data,
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
        );
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('deliveries');
        $order = $orderRepository->search($criteria, $this->context->createWithVersionId($versionId))->getEntities()->get($orderId);
        static::assertNotNull($order);
        static::assertNotNull($order->getDeliveries());
        static::assertCount(2, $order->getDeliveries());
        static::assertSame($order->getOrderDateTime()->format(Defaults::STORAGE_DATE_TIME_FORMAT), $orderDateTime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $firstDelivery = $order->getDeliveries()->first();
        $secondDelivery = $order->getDeliveries()->last();

        static::assertInstanceOf(OrderDeliveryEntity::class, $firstDelivery);
        static::assertInstanceOf(OrderDeliveryEntity::class, $secondDelivery);

        static::assertSame($firstDelivery->getShippingCosts()->getTotalPrice(), 5.0);
        static::assertSame($secondDelivery->getShippingCosts()->getTotalPrice(), -5.0);
    }

    /**
     * @return array<string, int|string>
     */
    private function createDeliveryTime(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }

    private function createShippingMethod(string $priceRuleId): string
    {
        $shippingMethodId = Uuid::randomHex();
        $deliveryTimeData = $this->createDeliveryTime();

        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = new \ReflectionProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $taxId = Uuid::randomHex();

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method',
            'technicalName' => 'shipping_test',
            'bindShippingfree' => false,
            'active' => true,
            'deliveryTime' => $deliveryTimeData,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.00,
                            'gross' => 10.00,
                            'linked' => false,
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 8.00,
                            'gross' => 8.00,
                            'linked' => false,
                        ],
                    ],
                    'calculationRule' => [
                        'name' => 'check',
                        'priority' => 10,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
            'taxId' => $taxId,
            'tax' => [
                'id' => $taxId,
                'taxRate' => 19,
                'name' => 'test',
            ],
        ];

        $this->shippingMethodRepository->create([$data], $this->context);

        return $shippingMethodId;
    }

    private function addSecondPriceRuleToShippingMethod(string $priceRuleId, string $shippingMethodId): ShippingMethodEntity
    {
        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method 2',
            'technicalName' => 'shipping_test2',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTime(),
            'active' => true,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 15.00,
                            'gross' => 15.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 0,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 20.00,
                            'gross' => 20.00,
                            'linked' => false,
                        ],
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $this->shippingMethodRepository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('priceRules');

        $shippingMethod = $this->shippingMethodRepository->search($criteria, $this->context)->getEntities()->get($shippingMethodId);
        static::assertNotNull($shippingMethod);

        return $shippingMethod;
    }

    private function addSecondShippingMethodPriceRule(string $priceRuleId, string $shippingMethodId): ShippingMethodEntity
    {
        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method 3',
            'technicalName' => 'shipping_test3',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTime(),
            'active' => true,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 15.00,
                            'gross' => 15.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 1,
                    'quantityEnd' => 9,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 10.00,
                            'gross' => 10.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => 1,
                    'quantityStart' => 10,
                    'quantityEnd' => 20,
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $this->shippingMethodRepository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('prices');
        $criteria->addAssociation('deliveryTime');

        $shippingMethod = $this->shippingMethodRepository->search($criteria, $this->context)->getEntities()->get($shippingMethodId);
        static::assertNotNull($shippingMethod);

        return $shippingMethod;
    }

    private function createTwoConditionsWithDifferentQuantities(string $priceRuleId, string $shippingMethodId, int $calculation): ShippingMethodEntity
    {
        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method 4',
            'technicalName' => 'shipping_test4',
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTime(),
            'active' => true,
            'prices' => [
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 15.00,
                            'gross' => 15.00,
                            'linked' => false,
                        ],
                    ],
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => $calculation,
                    'quantityStart' => 0,
                    'quantityEnd' => 70,
                ],
                [
                    'id' => Uuid::randomHex(),
                    'currencyPrice' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'net' => 9.99,
                            'gross' => 9.99,
                            'linked' => false,
                        ],
                    ],
                    'currencyId' => Defaults::CURRENCY,
                    'rule' => [
                        'id' => $priceRuleId,
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'true',
                            ],
                        ],
                    ],
                    'calculation' => $calculation,
                    'quantityStart' => 71,
                ],
            ],
            'availabilityRule' => [
                'id' => $priceRuleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
        ];

        $this->shippingMethodRepository->upsert([$data], $this->context);

        $criteria = new Criteria([$shippingMethodId]);
        $criteria->addAssociation('priceRules');
        $criteria->addAssociation('deliveryTime');

        $shippingMethod = $this->shippingMethodRepository->search($criteria, $this->context)->getEntities()->get($shippingMethodId);
        static::assertNotNull($shippingMethod);

        return $shippingMethod;
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = new \ReflectionProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => TestPaymentHandler::class,
            'name' => 'Payment',
            'technicalName' => 'payment_test',
            'active' => true,
            'position' => 0,
            'availabilityRule' => [
                'id' => $ruleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
            'channels' => [
                [
                    'id' => TestDefaults::CHANNEL,
                ],
            ],
        ];

        static::getContainer()->get('payment_method.repository')->create([$data], $this->context);

        return $paymentMethodId;
    }
}

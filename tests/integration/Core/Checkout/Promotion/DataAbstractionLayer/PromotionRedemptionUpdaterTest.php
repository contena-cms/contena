<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Promotion\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Contena\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Checkout\Promotion\PromotionEntity;
use Contena\Core\Checkout\Promotion\Subscriber\PromotionIndividualCodeRedeemer;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionRedemptionUpdaterTest extends TestCase
{
    use UserTestTrait;
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private IdsCollection $ids;

    private Connection $connection;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->channelContext = $this->createChannelContext();
    }

    public function testPromotionRedemptionUpdaterUpdateViaIndexer(): void
    {
        $this->createPromotionsAndOrder();

        $updater = static::getContainer()->get(PromotionRedemptionUpdater::class);
        $updater->update(
            [
                $this->ids->get('voucherA'),
                $this->ids->get('voucherB'),
                $this->ids->get('voucherD'),
                Uuid::randomHex(), // Test an invalid promotionId
            ],
            Context::createDefaultContext()
        );

        $this->assertUpdatedCounts();
    }

    public function testPromotionRedemptionUpdaterUpdateViaEntityWrittenEvent(): void
    {
        $this->createPromotionsAndOrder();

        $criteria = new Criteria([$this->ids->get('order')]);
        $criteria->addAssociation('lineItems');

        /** @var OrderEntity|null $order */
        $order = static::getContainer()
            ->get('order.repository')
            ->search($criteria, $this->channelContext->getContext())->getEntities()
            ->first();

        static::assertNotNull($order);

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $dispatcher->dispatch(new EntityWrittenEvent(UserDefinition::ENTITY_NAME, [new EntityWriteResult($this->ids->get('user'), [], UserDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)], $this->channelContext->getContext()));
        $this->assertNonUpdatedCounts();

        $dispatcher->dispatch(new EntityWrittenEvent(OrderDefinition::ENTITY_NAME, [new EntityWriteResult($order->getId(), [], OrderDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)], $this->channelContext->getContext()));
        $this->assertUpdatedCounts();
    }

    #[DataProvider('individualCodeDataProvider')]
    public function testIndividualCodeGotUserAssignment(string $orderCode): void
    {
        $this->createPromotionsAndOrder($orderCode);

        $voucherD = $this->ids->get('voucherD');

        $connection = static::getContainer()->get(Connection::class);

        $criteria = new Criteria([$this->ids->create('order')]);
        $criteria->addAssociation('lineItems');

        /** @var OrderEntity|null $order */
        $order = static::getContainer()
            ->get('order.repository')
            ->search($criteria, Context::createDefaultContext())->getEntities()
            ->first();

        static::assertNotNull($order);

        $lineItems = $order->getLineItems();

        static::assertNotNull($lineItems);

        $lineItem1 = $lineItems->getAt(0);
        $lineItem2 = $lineItems->getAt(1);
        $lineItem3 = $lineItems->getAt(2);
        $lineItem4 = $lineItems->getAt(3);

        static::assertNotNull($lineItem1);
        static::assertNotNull($lineItem2);
        static::assertNotNull($lineItem3);
        static::assertNotNull($lineItem4);

        $event = new EntityWrittenEvent('order_line_item', [
            new EntityWriteResult($lineItem1->getId(), $lineItem1->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult($lineItem2->getId(), $lineItem2->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult($lineItem3->getId(), $lineItem3->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult($lineItem4->getId(), $lineItem4->jsonSerialize(), OrderLineItemDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_INSERT),
        ], $this->channelContext->getContext());

        $updater = static::getContainer()->get(PromotionIndividualCodeRedeemer::class);
        $updater->onOrderLineItemWritten($event);

        $promotionIndividualCode = $connection->fetchAllAssociative(
            'SELECT `id`, `payload` FROM `promotion_individual_code` WHERE `promotion_id` = :id',
            ['id' => Uuid::fromHexToBytes($voucherD)]
        );

        $user = $connection->fetchAllAssociative(
            'SELECT `id`, `first_name`, `last_name` FROM user WHERE `id` = :id',
            ['id' => Uuid::fromHexToBytes($this->ids->get('user'))]
        );

        $promotionIndividualCodePayload = $this->createIndividualCodePayload($order->getId(), $user[0]);

        $expected_json = json_encode($promotionIndividualCodePayload, \JSON_THROW_ON_ERROR);
        static::assertIsString($expected_json);
        static::assertIsString($promotionIndividualCode[0]['payload']);

        static::assertCount(1, $promotionIndividualCode);
        static::assertJsonStringEqualsJsonString(
            $expected_json,
            $promotionIndividualCode[0]['payload']
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function individualCodeDataProvider(): iterable
    {
        yield 'stored casing' => ['test-FABPB-test'];
        yield 'different casing' => ['TEST-fabpb-TEST'];
    }

    public function testRemoveItemMultipleOrders(): void
    {
        $this->createPromotionsAndOrder();
        $firstUser = $this->ids->get('user');

        $this->ids->set('user', $this->createUser('second@example.com'));

        $secondUser = $this->ids->get('user');
        $secondOrderLineItemId = Uuid::randomHex();

        // Create second order with different user
        static::getContainer()->get('order.repository')->create(
            [[
                'id' => Uuid::randomHex(),
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'orderUser' => [
                    'userId' => $secondUser,
                    'email' => 'test@example.com',
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ],
                'stateId' => $this->fetchFirstIdFromTable('state_machine_state'),
                'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'channelId' => TestDefaults::CHANNEL,
                'billingAddressId' => Uuid::randomHex(),
                'addresses' => [
                    [
                        'id' => Uuid::randomHex(),
                        'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                        'countryId' => $this->fetchFirstIdFromTable('country'),
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $secondOrderLineItemId,
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => '',
                        'identifier' => $this->ids->get('voucherA'),
                        'quantity' => 1,
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherA'),
                            'code' => '',
                            'promotionCodeType' => 'global',
                        ],
                        'promotionId' => $this->ids->get('voucherA'),
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [],
                'context' => '{}',
                'payload' => '{}',
            ]],
            Context::createDefaultContext()
        );

        $promotionRepository = static::getContainer()->get('promotion.repository');

        $promotionA = $promotionRepository->search(new Criteria([$this->ids->get('voucherA')]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PromotionEntity::class, $promotionA);
        static::assertIsArray($promotionA->getOrdersPerUserCount());
        static::assertArrayHasKey($firstUser, $promotionA->getOrdersPerUserCount());
        static::assertArrayHasKey($secondUser, $promotionA->getOrdersPerUserCount());
        static::assertSame(2, $promotionA->getOrderCount());
        static::assertSame(1, $promotionA->getOrdersPerUserCount()[$firstUser]);
        static::assertSame(1, $promotionA->getOrdersPerUserCount()[$secondUser]);

        static::getContainer()
            ->get('order_line_item.repository')
            ->delete([['id' => $this->ids->get('voucherA')]], Context::createDefaultContext());

        $promotionALater = $promotionRepository->search(new Criteria([$this->ids->get('voucherA')]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PromotionEntity::class, $promotionALater);
        static::assertIsArray($promotionALater->getOrdersPerUserCount());
        static::assertArrayNotHasKey($firstUser, $promotionALater->getOrdersPerUserCount());
        static::assertArrayHasKey($secondUser, $promotionALater->getOrdersPerUserCount());
        static::assertSame(1, $promotionALater->getOrdersPerUserCount()[$secondUser]);
        static::assertSame(1, $promotionALater->getOrderCount());

        static::getContainer()
            ->get('order_line_item.repository')
            ->delete([['id' => $secondOrderLineItemId]], Context::createDefaultContext());

        $promotionAEvenLater = $promotionRepository->search(new Criteria([$this->ids->get('voucherA')]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PromotionEntity::class, $promotionAEvenLater);
        static::assertSame(0, $promotionAEvenLater->getOrderCount());
        static::assertNull($promotionAEvenLater->getOrdersPerUserCount());
    }

    public function testLineItemOfAnotherTypeCountsTowardsRedemptions(): void
    {
        $this->createPromotionsAndOrder();

        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $promotionId = $this->ids->create('customLineItemPromotion');
        $this->createPromotion($promotionId, $promotionId, $promotionRepository, $this->channelContext);

        $lineItemId = Uuid::randomHex();
        static::getContainer()->get('order_line_item.repository')->create([[
            'id' => $lineItemId,
            'orderId' => $this->ids->get('order'),
            'orderVersionId' => Defaults::LIVE_VERSION,
            'identifier' => $lineItemId,
            'type' => 'custom',
            'quantity' => 1,
            'position' => 1,
            'label' => 'label',
            'promotionId' => $promotionId,
            'price' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]], Context::createDefaultContext());

        static::assertSame(1, $this->fetchPromotion($promotionId)->getOrderCount());

        static::getContainer()
            ->get('order_line_item.repository')
            ->delete([['id' => $lineItemId]], Context::createDefaultContext());

        static::assertSame(0, $this->fetchPromotion($promotionId)->getOrderCount());
    }

    private function fetchPromotion(string $promotionId): PromotionEntity
    {
        /** @var EntityRepository<PromotionCollection> $promotionRepository */
        $promotionRepository = static::getContainer()->get('promotion.repository');

        $promotion = $promotionRepository
            ->search(new Criteria([$promotionId]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(PromotionEntity::class, $promotion);

        return $promotion;
    }

    private function createPromotionsAndOrder(string $orderCode = 'test-FABPB-test'): void
    {
        /** @var EntityRepository<PromotionCollection> */
        $promotionRepository = static::getContainer()->get('promotion.repository');

        /** @var EntityRepository<PromotionIndividualCodeCollection> */
        $promotionIndividualCodeRepository = static::getContainer()->get('promotion_individual_code.repository');

        $voucherA = $this->ids->create('voucherA');
        $voucherB = $this->ids->create('voucherB');
        $voucherD = $this->ids->create('voucherD');

        $this->createPromotion($voucherA, $voucherA, $promotionRepository, $this->channelContext);
        $this->createPromotion($voucherB, $voucherB, $promotionRepository, $this->channelContext);
        $this->createPromotion($voucherD, null, $promotionRepository, $this->channelContext);
        $this->createIndividualCode(
            $voucherD,
            'test-FABPB-test',
            $promotionIndividualCodeRepository,
            $this->channelContext->getContext()
        );

        $this->ids->set('user', $this->createUser('johndoe@example.com'));
        $this->createOrder($this->ids->get('user'), $orderCode);

        $lineItems = $this->connection->fetchAllAssociative('SELECT id FROM order_line_item;');

        static::assertCount(4, $lineItems);
    }

    private function assertUpdatedCounts(): void
    {
        $promotions = $this->connection->fetchAllAssociative('SELECT * FROM promotion;');

        static::assertCount(3, $promotions);

        $actualVoucherA = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherA') ? $promotions[0] : $promotions[1];
        static::assertNotCount(0, $actualVoucherA);
        static::assertSame('1', $actualVoucherA['order_count']);
        $userCount = json_decode((string) $actualVoucherA['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $userCount[$this->ids->get('user')]);

        $actualVoucherD = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherD') ? $promotions[0] : $promotions[1];
        static::assertNotCount(0, $actualVoucherD);
        static::assertSame('1', $actualVoucherD['order_count']);
        $userCount = json_decode((string) $actualVoucherD['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $userCount[$this->ids->get('user')]);

        $actualVoucherB = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherB') ? $promotions[0] : $promotions[1];
        static::assertNotCount(0, $actualVoucherB);
        static::assertSame('1', $actualVoucherB['order_count']);
        $userCount = json_decode((string) $actualVoucherB['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $userCount[$this->ids->get('user')]);
    }

    private function assertNonUpdatedCounts(): void
    {
        $promotions = $this->connection->fetchAllAssociative('SELECT * FROM promotion;');

        static::assertCount(3, $promotions);

        $actualVoucherA = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherA') ? $promotions[0] : $promotions[1];
        static::assertNotCount(0, $actualVoucherA);
        static::assertSame('1', $actualVoucherA['order_count']);
        $userCount = json_decode((string) $actualVoucherA['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $userCount[$this->ids->get('user')]);

        $actualVoucherD = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherD') ? $promotions[0] : $promotions[1];
        static::assertNotCount(0, $actualVoucherD);
        static::assertSame('1', $actualVoucherD['order_count']);
        $userCount = json_decode((string) $actualVoucherD['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $userCount[$this->ids->get('user')]);

        $actualVoucherB = Uuid::fromBytesToHex($promotions[0]['id']) === $this->ids->get('voucherB') ? $promotions[0] : $promotions[1];
        static::assertNotCount(0, $actualVoucherB);
        // voucherB is used twice, it's mean group by works
        static::assertSame('1', $actualVoucherB['order_count']);
        $userCount = json_decode((string) $actualVoucherB['orders_per_customer_count'], true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $userCount[$this->ids->get('user')]);
    }

    /**
     * @param array<string, string> $user
     *
     * @return array<string, string>
     */
    private function createIndividualCodePayload(string $orderId, array $user): array
    {
        return [
            'orderId' => $orderId,
            'userId' => Uuid::fromBytesToHex($user['id']),
            'userName' => $user['first_name'] . ' ' . $user['last_name'],
        ];
    }

    /**
     * @param array<mixed> $options
     */
    private function createChannelContext(array $options = []): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        $token = Uuid::randomHex();

        return $channelContextFactory->create($token, TestDefaults::CHANNEL, $options);
    }

    private function createOrder(string $userId, string $individualCode): void
    {
        static::getContainer()->get('order.repository')->create(
            [[
                'id' => $this->ids->create('order'),
                'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'orderUser' => [
                    'userId' => $userId,
                    'email' => 'test@example.com',
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                ],
                'stateId' => $this->fetchFirstIdFromTable('state_machine_state'),
                'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
                'currencyId' => Defaults::CURRENCY,
                'currencyFactor' => 1.0,
                'channelId' => TestDefaults::CHANNEL,
                'billingAddressId' => Uuid::randomHex(),
                'addresses' => [
                    [
                        'id' => Uuid::randomHex(),
                        'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Ebbinghoff 10',
                        'zipcode' => '48624',
                        'city' => 'Schöppingen',
                        'countryId' => $this->fetchFirstIdFromTable('country'),
                    ],
                ],
                'lineItems' => [
                    [
                        'id' => $this->ids->get('voucherA'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => '',
                        'identifier' => $this->ids->get('voucherA'),
                        'quantity' => 1,
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherA'),
                            'code' => '',
                            'promotionCodeType' => 'global',
                        ],
                        'promotionId' => $this->ids->get('voucherA'),
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $this->ids->get('voucherD'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => null,
                        'identifier' => $this->ids->get('voucherD'),
                        'quantity' => 1,
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherD'),
                            'code' => $individualCode,
                            'promotionCodeType' => 'individual',
                        ],
                        'promotionId' => $this->ids->get('voucherD'),
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $this->ids->get('voucherC'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('voucherC'),
                        'identifier' => $this->ids->get('voucherC'),
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherB'),
                            'code' => $this->ids->get('voucherC'),
                        ],
                        'promotionId' => $this->ids->get('voucherB'),
                        'quantity' => 1,
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                    [
                        'id' => $this->ids->get('voucherB'),
                        'type' => LineItem::PROMOTION_LINE_ITEM_TYPE,
                        'code' => $this->ids->get('voucherB'),
                        'identifier' => $this->ids->get('voucherB'),
                        'payload' => [
                            'promotionId' => $this->ids->get('voucherB'),
                            'code' => $this->ids->get('voucherB'),
                        ],
                        'promotionId' => $this->ids->get('voucherB'),
                        'quantity' => 1,
                        'label' => 'label',
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                        'priceDefinition' => new QuantityPriceDefinition(200, new TaxRuleCollection(), 2),
                    ],
                ],
                'deliveries' => [],
                'context' => '{}',
                'payload' => '{}',
            ]],
            Context::createDefaultContext()
        );
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        return Uuid::fromBytesToHex((string) $this->connection->fetchOne('SELECT id FROM ' . $table . ' LIMIT 1'));
    }
}

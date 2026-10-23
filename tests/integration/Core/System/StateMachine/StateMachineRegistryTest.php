<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\StateMachine;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Contena\Core\System\StateMachine\StateMachineCollection;
use Contena\Core\System\StateMachine\StateMachineException;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Core\Test\Integration\Builder\Order\OrderBuilder;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class StateMachineRegistryTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    private string $stateMachineId;

    private string $openId;

    private string $inProgressId;

    private string $closedId;

    private string $stateMachineName;

    private StateMachineRegistry $stateMachineRegistry;

    /**
     * @var EntityRepository<StateMachineCollection>
     */
    private EntityRepository $stateMachineRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);
        $this->stateMachineRepository = static::getContainer()->get('state_machine.repository');

        $this->stateMachineName = 'test_state_machine';
        $this->stateMachineId = Uuid::randomHex();
        $this->openId = Uuid::randomHex();
        $this->inProgressId = Uuid::randomHex();
        $this->closedId = Uuid::randomHex();

        $nullableTable = <<<EOF
DROP TABLE IF EXISTS _test_nullable;
CREATE TABLE `_test_nullable` (
  `id` varbinary(16) NOT NULL,
  `state` varchar(255) NULL,
  PRIMARY KEY `id` (`id`)
);
EOF;
        $this->connection->executeStatement($nullableTable);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `_test_nullable`');
    }

    public function testNonExistingStateMachine(): void
    {
        $this->expectException(StateMachineException::class);

        $context = Context::createDefaultContext();

        $this->stateMachineRegistry->getStateMachine('wusel', $context);
    }

    public function testStateMachineShouldIncludeRelations(): void
    {
        $context = Context::createDefaultContext();
        $this->createStateMachine($context);

        $stateMachine = $this->stateMachineRegistry->getStateMachine($this->stateMachineName, $context);

        static::assertNotNull($stateMachine->getStates());
        static::assertCount(3, $stateMachine->getStates());
        static::assertNotNull($stateMachine->getTransitions());
        static::assertCount(4, $stateMachine->getTransitions());
    }

    public function testStateMachineAvailableTransitionShouldIncludeReOpenAndReTourTransition(): void
    {
        $this->createOrderWithPartiallyReturnedDeliveryState();
        $availableTransitions = $this->stateMachineRegistry->getAvailableTransitions('order_delivery', $this->fetchFirstIdFromTable('order_delivery'), 'stateId', Context::createDefaultContext());

        static::assertNotCount(0, $availableTransitions);
        static::assertCount(2, $availableTransitions);

        $reopenActionExisted = false;
        $retourActionExisted = false;

        /** @var StateMachineTransitionEntity $transition */
        foreach ($availableTransitions as $transition) {
            if ($transition->getActionName() === 'reopen') {
                $reopenActionExisted = true;
                static::assertSame(OrderDeliveryStates::STATE_OPEN, $transition->getToStateMachineState()?->getTechnicalName());
            }

            if ($transition->getActionName() === 'retour') {
                $retourActionExisted = true;
                static::assertSame(OrderDeliveryStates::STATE_RETURNED, $transition->getToStateMachineState()?->getTechnicalName());
            }
        }

        static::assertTrue($reopenActionExisted);
        static::assertTrue($retourActionExisted);
    }

    public function testStateMachineStateRetourTransitionFromReturnedPartially(): void
    {
        $orderDeliveryId = $this->createOrderWithPartiallyReturnedDeliveryState();
        $transition = new Transition('order_delivery', $orderDeliveryId, 'retour', 'stateId');
        $stateCollection = $this->stateMachineRegistry->transition($transition, Context::createDefaultContext());

        static::assertNotCount(0, $stateCollection);
        static::assertNotNull($stateCollection->get('fromPlace'));
        static::assertNotNull($stateCollection->get('toPlace'));
        $fromPlace = $stateCollection->get('fromPlace');
        $toPlace = $stateCollection->get('toPlace');
        static::assertSame(OrderDeliveryStates::STATE_PARTIALLY_RETURNED, $fromPlace->getTechnicalName());
        static::assertSame(OrderDeliveryStates::STATE_RETURNED, $toPlace->getTechnicalName());
    }

    public function testStateMachineRegistryUnnecessaryTransition(): void
    {
        $orderDeliveryId = $this->createOrderWithPartiallyReturnedDeliveryState();
        $transition = new Transition('order_delivery', $orderDeliveryId, 'retour_partially', 'stateId');
        $stateCollection = $this->stateMachineRegistry->transition($transition, Context::createDefaultContext());

        static::assertNotCount(0, $stateCollection);
        static::assertNotNull($stateCollection->get('fromPlace'));
        static::assertNotNull($stateCollection->get('toPlace'));
        $fromPlace = $stateCollection->get('fromPlace');
        $toPlace = $stateCollection->get('toPlace');
        static::assertSame(OrderDeliveryStates::STATE_PARTIALLY_RETURNED, $fromPlace->getTechnicalName());
        static::assertSame(OrderDeliveryStates::STATE_PARTIALLY_RETURNED, $toPlace->getTechnicalName());
    }

    public function testStateMachineTransitionUpdatesEntityUpdatedAt(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $orderBuilder = new OrderBuilder($ids, 'o-1');

        /** @var EntityRepository<OrderCollection> $orderRepo */
        $orderRepo = self::getContainer()->get('order.repository');
        static::assertInstanceOf(EntityRepository::class, $orderRepo);
        $orderRepo->create([$orderBuilder->build()], Context::createCLIContext());

        $this->connection->executeStatement(
            'UPDATE `order` SET `updated_at` = NULL WHERE `id` = :id AND `version_id` = :version',
            [
                'id' => Uuid::fromHexToBytes($ids->get('o-1')),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]
        );

        $orderBefore = $orderRepo->search(new Criteria([$ids->get('o-1')]), $context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $orderBefore);
        static::assertNull($orderBefore->getUpdatedAt());

        $stateCollection = $this->stateMachineRegistry->transition(
            new Transition('order', $ids->get('o-1'), 'process', 'stateId'),
            $context
        );

        $toPlace = $stateCollection->get('toPlace');
        static::assertNotNull($toPlace);

        $orderAfter = $orderRepo->search(new Criteria([$ids->get('o-1')]), $context)->getEntities()->first();
        static::assertInstanceOf(OrderEntity::class, $orderAfter);

        static::assertSame($toPlace->getId(), $orderAfter->getStateId());
        static::assertNotNull($orderAfter->getUpdatedAt());
    }

    public function testStateMachineTransitionStoresTheActorTheSourceAndTheInternalComment(): void
    {
        $ids = new IdsCollection();

        $admin_userRepo = self::getContainer()->get('admin_user.repository');
        static::assertInstanceOf(EntityRepository::class, $admin_userRepo);

        $admin_userId = $admin_userRepo->searchIds((new Criteria())->setLimit(1), Context::createDefaultContext())->firstId();

        $integration = [
            'id' => $ids->get('integration-1'),
            'label' => 'Integration 1',
            'accessKey' => 'test123',
            'secretAccessKey' => TestDefaults::HASHED_PASSWORD,
        ];

        $integrationRepo = self::getContainer()->get('integration.repository');
        static::assertInstanceOf(EntityRepository::class, $integrationRepo);
        $integrationRepo->create([$integration], Context::createDefaultContext());

        static::assertNotNull($admin_userId);

        $orderBuilder = new OrderBuilder($ids, 'o-1');

        $orderRepo = self::getContainer()->get('order.repository');
        static::assertInstanceOf(EntityRepository::class, $orderRepo);
        $orderRepo->create([$orderBuilder->build()], Context::createCLIContext());

        $context = new Context(
            new AdminApiSource($admin_userId, $ids->get('integration-1'))
        );

        $stateMachineRegistry = self::getContainer()->get(StateMachineRegistry::class);
        static::assertInstanceOf(StateMachineRegistry::class, $stateMachineRegistry);
        $stateMachineRegistry->transition(
            new Transition('order', $ids->get('o-1'), 'process', 'stateId', 'internal comment'),
            $context
        );

        $connection = self::getContainer()->get(Connection::class);
        static::assertInstanceOf(Connection::class, $connection);

        $historyData = $connection->fetchAssociative('SELECT LOWER(HEX(integration_id)) as integration_id, LOWER(HEX(admin_user_id)) as admin_user_id, source_type, internal_comment FROM `state_machine_history` WHERE referenced_id = :id AND referenced_version_id = :version ORDER BY created_at DESC LIMIT 1', [
            'id' => Uuid::fromHexToBytes($ids->get('o-1')),
            'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ]);

        static::assertNotFalse($historyData);
        static::assertSame([
            'integration_id' => $ids->get('integration-1'),
            'admin_user_id' => $admin_userId,
            'source_type' => 'admin-api',
            'internal_comment' => 'internal comment',
        ], $historyData);
    }

    private function createOrderWithPartiallyReturnedDeliveryState(): string
    {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $orderLineItemId = Uuid::randomHex();

        $connection = static::getContainer()->get(Connection::class);

        $orderStateMachineId = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :name', ['name' => 'order.state']);
        $orderOpen = $connection->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => OrderStates::STATE_OPEN, 'id' => $orderStateMachineId]);

        $deliveryStateMachineId = $connection->fetchOne('SELECT id FROM state_machine WHERE technical_name = :name', ['name' => 'order_delivery.state']);
        /** @var string $returnedPartially */
        $returnedPartially = $connection->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :name AND state_machine_id = :id', ['name' => OrderDeliveryStates::STATE_PARTIALLY_RETURNED, 'id' => $deliveryStateMachineId]);
        $returnedPartially = Uuid::fromBytesToHex($returnedPartially);

        $orderDeliveryId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(
                10,
                10,
                10,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_NET
            ),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderUser' => [
                'userId' => $this->createUser(),
                'email' => 'test@example.com',
                'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'orderNumber' => Uuid::randomHex(),
            'stateId' => Uuid::fromBytesToHex($orderOpen),
            'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'channelId' => TestDefaults::CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
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
                    'id' => $orderLineItemId,
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection(), 2),
                    'good' => true,
                ],
            ],
            'deliveries' => [
                [
                    'id' => $orderDeliveryId,
                    'shippingMethodId' => $this->getValidShippingMethodId(),
                    'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => date(\DATE_ATOM),
                    'shippingDateLatest' => date(\DATE_ATOM),
                    'stateId' => $returnedPartially,
                    'shippingOrderAddress' => [
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'Floy',
                        'lastName' => 'Glover',
                        'zipcode' => '59438-0403',
                        'city' => 'Stellaberg',
                        'street' => 'street',
                        'country' => [
                            'name' => 'kasachstan',
                            'id' => $this->getValidCountryId(),
                        ],
                    ],
                    'positions' => [
                        [
                            'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                            'orderLineItemId' => $orderLineItemId,
                        ],
                    ],
                ],
            ],
            'context' => '{}',
            'payload' => '{}',
        ];

        static::getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());

        return $orderDeliveryId;
    }

    private function createStateMachine(Context $context): void
    {
        $this->stateMachineRepository->upsert([
            [
                'id' => $this->stateMachineId,
                'technicalName' => $this->stateMachineName,
                'translations' => [
                    'en-GB' => ['name' => 'Order state'],
                    'de-DE' => ['name' => 'Bestellungsstatus'],
                ],
                'states' => [
                    ['id' => $this->openId, 'technicalName' => OrderDeliveryStates::STATE_OPEN, 'name' => OrderDeliveryStates::STATE_OPEN],
                    ['id' => $this->inProgressId, 'technicalName' => 'in_progress', 'name' => 'In progress'],
                    ['id' => $this->closedId, 'technicalName' => 'closed', 'name' => 'Closed'],
                ],
                'transitions' => [
                    ['actionName' => 'start', 'fromStateId' => $this->openId, 'toStateId' => $this->inProgressId],

                    ['actionName' => 'reopen', 'fromStateId' => $this->inProgressId, 'toStateId' => $this->openId],
                    ['actionName' => 'close', 'fromStateId' => $this->inProgressId, 'toStateId' => $this->closedId],

                    ['actionName' => 'reopen', 'fromStateId' => $this->closedId, 'toStateId' => $this->openId],
                ],
            ],
        ], $context);
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        $connection = static::getContainer()->get(Connection::class);

        return Uuid::fromBytesToHex((string) $connection->fetchOne('SELECT id FROM ' . $table . ' LIMIT 1'));
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
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('user.repository')->upsert([$user], Context::createDefaultContext());

        return $userId;
    }
}

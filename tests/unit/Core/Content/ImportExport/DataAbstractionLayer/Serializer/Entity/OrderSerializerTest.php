<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderUser\OrderUserEntity;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\OrderSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(OrderSerializer::class)]
class OrderSerializerTest extends TestCase
{

    protected function setUp(): void
    {
        $this->serializer = new OrderSerializer();
        $this->serializer->setRegistry(static::createStub(SerializerRegistry::class));
    }

    public function testSupports(): void
    {
        static::assertTrue($this->serializer->supports('order'));
        static::assertFalse($this->serializer->supports('not_order'));
    }

    /**
     * @param array<mixed>|Struct|null $entity
     * @param array<mixed> $expected
     */
    #[DataProvider('serializeDataProvider')]
    public function testSerialize($entity, array $expected): void
    {
        $logEntity = new ImportExportLogEntity();
        $logEntity->setId(Uuid::randomHex());
        $config = Config::fromLog($logEntity);
        $definition = new OrderDefinition();
        $definition->compile(static::createStub(DefinitionInstanceRegistry::class));

        $result = iterator_to_array($this->serializer->serialize($config, $definition, $entity));

        static::assertEquals($expected, $result);
    }

    /**
     * @return iterable<string, array{entity: Struct|array<mixed>|null, expected: array<string, mixed>}>
     */
    public static function serializeDataProvider(): iterable
    {
        yield 'without entity' => [
            'entity' => null,
            'expected' => [],
        ];

        yield 'with array record' => [
            'entity' => [
                'orderNumber' => 'NUM-1',
            ],
            'expected' => [
                'orderNumber' => 'NUM-1',
            ],
        ];

        yield 'with entity' => [
            'entity' => self::createOrderEntity(),
            'expected' => self::getExpected(),
        ];

        yield 'with order empty line items' => [
            'entity' => self::createOrderEntity([
                'lineItems' => new OrderLineItemCollection(),
            ]),
            'expected' => self::getExpected(),
        ];

        yield 'with order line items' => [
            'entity' => self::createOrderEntity([
                'lineItems' => self::createLineItems(),
            ]),
            'expected' => self::getExpected([
                'lineItems' => '3x |2x ',
            ]),
        ];

        yield 'with order empty deliveries' => [
            'entity' => self::createOrderEntity([
                'deliveries' => new OrderDeliveryCollection(),
            ]),
            'expected' => self::getExpected([
                'deliveries' => new OrderDeliveryCollection(),
            ]),
        ];

        yield 'with order empty transactions' => [
            'entity' => self::createOrderEntity([
                'transactions' => new OrderTransactionCollection(),
            ]),
            'expected' => self::getExpected([
                'transactions' => new OrderTransactionCollection(),
            ]),
        ];

        yield 'with order item rounding and total rounding' => [
            'entity' => self::createOrderEntity([
                'itemRounding' => self::createItemRounding(),
                'totalRounding' => self::createTotalRounding(),
            ]),
            'expected' => self::getExpected([
                'itemRounding' => [
                    'extensions' => [],
                    'decimals' => 2,
                    'interval' => 0.01,
                    'roundForNet' => true,
                ],
                'totalRounding' => [
                    'extensions' => [],
                    'decimals' => 2,
                    'interval' => 0.1,
                    'roundForNet' => false,
                ],
            ]),
        ];

        yield 'with order deliveries' => [
            'entity' => self::createOrderEntity([
                'deliveries' => self::createDeliveries(),
            ]),
            'expected' => self::getExpected([
                'deliveries' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'delivery-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'shippingMethodId' => 'shipping-method-id',
                    'trackingCodes' => 'CODE-1|CODE-2',
                    'shippingCosts' => 1.0,
                    'shippingOrderAddress' => null,
                    'stateId' => '',
                    'stateMachineState' => null,
                    'shippingMethod' => null,
                    'order' => null,
                    'positions' => new OrderDeliveryPositionCollection(),
                    'id' => 'delivery-1',
                    'customFields' => null,
                    'primaryOrder' => null,
                ],
            ]),
        ];

        yield 'with order transactions' => [
            'entity' => self::createOrderEntity([
                'transactions' => self::createTransactions(),
            ]),
            'expected' => self::getExpected([
                'transactions' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'transaction-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'amount' => 42.0,
                    'paymentMethod' => null,
                    'order' => null,
                    'stateMachineState' => [
                        'extensions' => [],
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => null,
                        'updatedAt' => null,
                        'name' => null,
                        'stateMachine' => null,
                        'fromStateMachineTransitions' => null,
                        'toStateMachineTransitions' => null,
                        'orders' => null,
                        'orderTransactionCaptures' => null,
                        'orderTransactionCaptureRefunds' => null,
                        'orderTransactions' => null,
                        'orderDeliveries' => null,
                        'fromStateMachineHistoryEntries' => null,
                        'toStateMachineHistoryEntries' => null,
                        'customFields' => null,
                        'translations' => null,
                    ],
                    'captures' => null,
                    'customFields' => null,
                    'id' => 'transaction-1',
                    'validationData' => [],
                    'primaryOrder' => null,
                ],
            ]),
        ];

        yield 'with order with line items and deliveries' => [
            'entity' => self::createOrderEntity([
                'lineItems' => self::createLineItems(),
                'deliveries' => self::createDeliveries(),
            ]),
            'expected' => self::getExpected([
                'lineItems' => '3x |2x ',
                'deliveries' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'delivery-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'shippingMethodId' => 'shipping-method-id',
                    'trackingCodes' => 'CODE-1|CODE-2',
                    'shippingCosts' => 1.0,
                    'shippingOrderAddress' => null,
                    'stateId' => '',
                    'stateMachineState' => null,
                    'shippingMethod' => null,
                    'order' => null,
                    'positions' => new OrderDeliveryPositionCollection(),
                    'id' => 'delivery-1',
                    'customFields' => null,
                    'primaryOrder' => null,
                ],
            ]),
        ];

        yield 'with order with line items and deliveries and transactions' => [
            'entity' => self::createOrderEntity([
                'lineItems' => self::createLineItems(),
                'deliveries' => self::createDeliveries(),
                'transactions' => self::createTransactions(),
            ]),
            'expected' => self::getExpected([
                'lineItems' => '3x |2x ',
                'deliveries' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'delivery-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'shippingMethodId' => 'shipping-method-id',
                    'trackingCodes' => 'CODE-1|CODE-2',
                    'shippingCosts' => 1.0,
                    'shippingOrderAddress' => null,
                    'stateId' => '',
                    'stateMachineState' => null,
                    'shippingMethod' => null,
                    'order' => null,
                    'positions' => new OrderDeliveryPositionCollection(),
                    'id' => 'delivery-1',
                    'customFields' => null,
                    'primaryOrder' => null,
                ],
                'transactions' => [
                    'extensions' => [],
                    '_uniqueIdentifier' => 'transaction-1',
                    'versionId' => null,
                    'translated' => [],
                    'createdAt' => null,
                    'updatedAt' => null,
                    'amount' => 42.0,
                    'paymentMethod' => null,
                    'order' => null,
                    'stateMachineState' => [
                        'extensions' => [],
                        'versionId' => null,
                        'translated' => [],
                        'createdAt' => null,
                        'updatedAt' => null,
                        'name' => null,
                        'stateMachine' => null,
                        'fromStateMachineTransitions' => null,
                        'toStateMachineTransitions' => null,
                        'orders' => null,
                        'orderTransactionCaptures' => null,
                        'orderTransactionCaptureRefunds' => null,
                        'orderTransactions' => null,
                        'orderDeliveries' => null,
                        'fromStateMachineHistoryEntries' => null,
                        'toStateMachineHistoryEntries' => null,
                        'customFields' => null,
                        'translations' => null,
                    ],
                    'captures' => null,
                    'customFields' => null,
                    'id' => 'transaction-1',
                    'validationData' => [],
                    'primaryOrder' => null,
                ],
            ]),
        ];
    }



    /**
     * @param array<string, mixed> $overrided
     *
     * @return array<string, mixed>
     */
    private static function getExpected(array $overrided = []): array
    {
        return array_merge(
            [
                'updatedBy' => null,
                'updatedById' => null,
                'customFields' => null,
                'translated' => [],
                'extensions' => [],
                'versionId' => null,
                'createdAt' => null,
                'updatedAt' => null,
                'currency' => null,
                'taxStatus' => null,
                'language' => null,
                'channel' => null,
                'billingAddress' => null,
                'deliveries' => null,
                'lineItems' => null,
                'deepLinkCode' => null,
                'stateMachineState' => null,
                'documents' => null,
                'tags' => null,
                'createdById' => null,
                'createdBy' => null,
                'price' => new CartPrice(
                    0,
                    0,
                    0,
                    'gross',
                ),
                'shippingCosts' => 0.0,
                'currencyId' => '',
                'currencyFactor' => 0.0,
                'channelId' => TestDefaults::CHANNEL,
                'userComment' => null,
                'affiliateCode' => null,
                'campaignCode' => null,
                'itemRounding' => null,
                'totalRounding' => null,
                'orderUser' => (new OrderUserEntity())->assign([
                    'id' => 'order-user-id',
                    'company' => null,
                    'customFields' => null,
                    'userId' => 'user-id',
                    'userNumber' => 'user-number',
                    'email' => 'user-email',
                    'name' => 'user-first-name',
                    'lastName' => 'user-last-name',
                    'remoteAddress' => null,
                    'salutationId' => 'user-salutation-id',
                    'title' => null,
                    'vatIds' => null,
                ]),
                'transactions' => null,
                'orderNumber' => '10000',
                'ruleIds' => [],
                'taxCalculationType' => null,
                'addresses' => new OrderAddressCollection([
                ]),
                'source' => null,
                'internalComment' => null,
            ],
            $overrided,
            [
                'primaryOrderDelivery' => null,
                'primaryOrderDeliveryId' => null,
                'primaryOrderTransaction' => null,
                'primaryOrderTransactionId' => null,
                'primaryOrderDeliveryVersionId' => null,
                'primaryOrderTransactionVersionId' => null,
            ]
        );
    }



    private static function createItemRounding(): CashRoundingConfig
    {
        return new CashRoundingConfig(2, 0.01, true);
    }

    private static function createTotalRounding(): CashRoundingConfig
    {
        return new CashRoundingConfig(2, 0.1, false);
    }
}

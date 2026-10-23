<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\DocumentV2\DocumentDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderTag\OrderTagDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;

final class OrderAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly IteratorFactory $factory,
        private readonly EntityRepository $repository,
        private readonly int $indexingBatchSize
    ) {
    }

    public function getDecorated(): AbstractAdminIndexer
    {
        throw new DecorationPatternException(self::class);
    }

    public function getEntity(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'order-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize, Defaults::LIVE_VERSION);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $orderIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'orderNumber',
            'amountTotal',
            'orderDateTime',
            'stateId',
        ]);

        $addresses = $event->getPrimaryKeysWithPropertyChange(OrderAddressDefinition::ENTITY_NAME, [
            'city',
            'street',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryId',
            'orderId',
        ]);

        $orderDocuments = $event->getPrimaryKeysWithPropertyChange(DocumentDefinition::ENTITY_NAME, [
            'config',
            'orderId',
        ]);

        $transactions = $event->getPrimaryKeysWithPropertyChange(OrderTransactionDefinition::ENTITY_NAME, [
            'stateId',
        ]);

        $deliveries = $event->getPrimaryKeysWithPropertyChange(OrderDeliveryDefinition::ENTITY_NAME, [
            'stateId',
        ]);

        if ($addresses !== [] || $orderDocuments !== [] || $transactions !== [] || $deliveries !== []) {
            $orderIds = array_merge($orderIds, $event->getPrimaryKeys($this->getEntity()));
        }

        $tags = $event->getPrimaryKeysWithPropertyChange(OrderTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['orderId'])) {
                $orderIds[] = $pks['orderId'];
            }
        }

        return array_values(array_unique(array_filter($orderIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'orderNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'amountTotal' => AbstractElasticsearchDefinition::FLOAT_FIELD,
            'orderDate' => ElasticsearchFieldBuilder::datetime(),
            'orderDateTime' => ElasticsearchFieldBuilder::datetime(),
            'stateId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'stateMachineState' => ElasticsearchFieldBuilder::nested(),
            'channelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'affiliateCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'campaignCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
            'billingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'orderUser' => ElasticsearchFieldBuilder::nested([
                'user' => ElasticsearchFieldBuilder::nested([
                    'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                    'userNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                ]),
            ]),
            'lineItems' => ElasticsearchFieldBuilder::nested([
                'blogId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'payload' => [
                    'properties' => [
                        'code' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                    ],
                ],
            ]),
            'primaryOrderTransaction' => ElasticsearchFieldBuilder::nested([
                'stateMachineState' => ElasticsearchFieldBuilder::nested(),
                'paymentMethodId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'primaryOrderDelivery' => ElasticsearchFieldBuilder::nested([
                'stateMachineState' => ElasticsearchFieldBuilder::nested(),
                'shippingMethodId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                'shippingOrderAddress' => ElasticsearchFieldBuilder::nested([
                    'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
                ]),
            ]),
            'documents' => ElasticsearchFieldBuilder::nested(),
        ];

        $mapping['properties'] ??= [];
        $mapping['properties'] = array_merge($mapping['properties'], $override);

        return $mapping;
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    /**
     * @return array<string, array{
     *     id: string,
     *     text: string,
     *     completion: list<string>,
     *     orderNumber?: mixed,
     *     amountTotal?: float|null,
     *     orderDate?: string|null,
     *     orderDateTime?: string|null,
     *     stateId?: mixed,
     *     stateMachineState?: array{
     *         id: mixed,
     *         _count: int
     *     }|null,
     *     channelId?: mixed,
     *     affiliateCode?: mixed,
     *     campaignCode?: mixed,
     *     tags?: list<array{id: string, _count: int}>,
     *     billingAddress?: array{
     *         id: string,
     *         _count: int,
     *         countryId: string
     *     }|null,
     *     orderUser?: array{
     *         id: string,
     *         _count: int,
     *         user: array{
     *             id: string,
     *             _count: int,
     *             groupId: string,
     *             userNumber: string
     *         }|null
     *     }|null,
     *     lineItems?: list<array{
     *         id: string,
     *         _count: int,
     *         blogId: string|null,
     *         payload: array{code: string|null}
     *     }>,
     *     primaryOrderTransaction?: array{
     *         id: string,
     *         _count: int,
     *         stateMachineState: array{id: string, _count: int},
     *         paymentMethodId: string
     *     }|null,
     *     primaryOrderDelivery?: array{
     *         id: string,
     *         _count: int,
     *         stateMachineState: array{id: string, _count: int},
     *         shippingMethodId: string,
     *         shippingOrderAddress: array{id: string, _count: int, countryId: string}
     *     }|null,
     *     documents?: list<array{id: string, _count: int}>,
     *     createdAt?: string|null
     * }>
     */
    public function fetch(array $ids): array
    {
        $baseSql = <<<'SQL'
            SELECT LOWER(HEX(`order`.id)) as id,
                   tag_agg.tags as tags,
                   tag_agg.tagIds as tagIds,
                   address_agg.country as country,
                   address_agg.city as city,
                   address_agg.street as street,
                   address_agg.zipcode as zipcode,
                   address_agg.phone_number as phone_number,
                   address_agg.additional_address_line1 as additional_address_line1,
                   address_agg.additional_address_line2 as additional_address_line2,
                   document_agg.documentNumber as documentNumber,
                   order_customer.first_name,
                   order_customer.last_name,
                   order_customer.email,
                   order_customer.company,
                   order_customer.user_number,
                   `order`.order_number,
                   `order`.amount_total,
                   `order`.order_date_time,
                   LOWER(HEX(`order`.state_id)) AS stateId,
                   LOWER(HEX(`order`.channel_id)) AS channelId,
                   `order`.affiliate_code AS affiliateCode,
                   `order`.campaign_code AS campaignCode,
                   `order`.created_at as createdAt,
                   primary_delivery.tracking_codes,
                   document_agg.documentIds as documentIds,
                   LOWER(HEX(billing_address.id)) as billingAddressId,
                   LOWER(HEX(billing_address.country_id)) as billingAddressCountryId,
                   LOWER(HEX(order_customer.id)) as orderUserId,
                   LOWER(HEX(order_customer.user_id)) as userId,
                   LOWER(HEX(user.user_group_id)) as userGroupId,
                   user.user_number as liveUserNumber,
                   line_item_agg.lineItems as lineItems,
                   LOWER(HEX(primary_transaction.id)) as primaryTransactionId,
                   LOWER(HEX(primary_transaction.state_id)) as primaryTransactionStateId,
                   LOWER(HEX(primary_transaction.payment_method_id)) as primaryTransactionPaymentMethodId,
                   LOWER(HEX(primary_delivery.id)) as primaryDeliveryId,
                   LOWER(HEX(primary_delivery.state_id)) as primaryDeliveryStateId,
                   LOWER(HEX(primary_delivery.shipping_method_id)) as primaryDeliveryShippingMethodId,
                   LOWER(HEX(primary_delivery_address.country_id)) as primaryDeliveryCountryId
            FROM `order`
                LEFT JOIN order_customer
                    ON `order`.id = order_customer.order_id AND `order`.version_id = order_customer.order_version_id
                LEFT JOIN (
                    SELECT order_tag.order_id,
                           order_tag.order_version_id,
                           GROUP_CONCAT(DISTINCT tag.name SEPARATOR ' ') as tags,
                           GROUP_CONCAT(LOWER(HEX(tag.id)) SEPARATOR ' ') as tagIds
                    FROM order_tag
                    LEFT JOIN tag
                        ON order_tag.tag_id = tag.id
                    WHERE order_tag.order_id IN (:ids)
                    AND order_tag.order_version_id = :versionId
                    GROUP BY order_tag.order_id, order_tag.order_version_id
                ) as tag_agg
                    ON `order`.id = tag_agg.order_id AND `order`.version_id = tag_agg.order_version_id
                LEFT JOIN (
                    SELECT order_address.order_id,
                           order_address.order_version_id,
                           GROUP_CONCAT(DISTINCT country_translation.name SEPARATOR ' ') as country,
                           GROUP_CONCAT(DISTINCT order_address.city SEPARATOR ' ') as city,
                           GROUP_CONCAT(DISTINCT order_address.street SEPARATOR ' ') as street,
                           GROUP_CONCAT(DISTINCT order_address.zipcode SEPARATOR ' ') as zipcode,
                           GROUP_CONCAT(DISTINCT order_address.phone_number SEPARATOR ' ') as phone_number,
                           GROUP_CONCAT(DISTINCT order_address.additional_address_line1 SEPARATOR ' ') as additional_address_line1,
                           GROUP_CONCAT(DISTINCT order_address.additional_address_line2 SEPARATOR ' ') as additional_address_line2
                    FROM order_address
                    LEFT JOIN country
                        ON order_address.country_id = country.id
                    LEFT JOIN country_translation
                        ON country.id = country_translation.country_id
                    WHERE order_address.order_id IN (:ids)
                    AND order_address.order_version_id = :versionId
                    GROUP BY order_address.order_id, order_address.order_version_id
                ) as address_agg
                    ON `order`.id = address_agg.order_id AND `order`.version_id = address_agg.order_version_id
                LEFT JOIN (
                    SELECT document.order_id,
                           GROUP_CONCAT(DISTINCT document.document_number SEPARATOR ' ') as documentNumber,
                           GROUP_CONCAT(DISTINCT LOWER(HEX(document.id)) SEPARATOR ' ') as documentIds
                    FROM document
                    WHERE document.order_id IN (:ids)
                    GROUP BY document.order_id
                ) as document_agg
                    ON `order`.id = document_agg.order_id
                LEFT JOIN order_address AS billing_address
                    ON `order`.billing_address_id = billing_address.id AND `order`.billing_address_version_id = billing_address.version_id
                LEFT JOIN user
                    ON order_customer.user_id = user.id
                LEFT JOIN (
                    SELECT order_line_item.order_id,
                           order_line_item.order_version_id,
                           CONCAT(
                               '[',
                               GROUP_CONCAT(
                                   DISTINCT JSON_OBJECT(
                                       'id', LOWER(HEX(order_line_item.id)),
                                       'blogId', LOWER(HEX(order_line_item.blog_id)),
                                       'code', CASE WHEN order_line_item.promotion_id IS NOT NULL THEN JSON_UNQUOTE(JSON_EXTRACT(order_line_item.payload, '$.code')) END
                                   ) ORDER BY NULL
                               ),
                               ']'
                           ) as lineItems
                    FROM order_line_item
                    WHERE order_line_item.order_id IN (:ids)
                    AND order_line_item.order_version_id = :versionId
                    GROUP BY order_line_item.order_id, order_line_item.order_version_id
                ) as line_item_agg
                    ON `order`.id = line_item_agg.order_id AND `order`.version_id = line_item_agg.order_version_id
                LEFT JOIN order_transaction AS primary_transaction
                    ON `order`.primary_order_transaction_id = primary_transaction.id AND `order`.primary_order_transaction_version_id = primary_transaction.version_id
                LEFT JOIN order_delivery AS primary_delivery
                    ON `order`.primary_order_delivery_id = primary_delivery.id AND `order`.primary_order_delivery_version_id = primary_delivery.version_id
                LEFT JOIN order_address AS primary_delivery_address
                    ON primary_delivery.shipping_order_address_id = primary_delivery_address.id AND primary_delivery.shipping_order_address_version_id = primary_delivery_address.version_id
            WHERE `order`.id IN (:ids)
            AND `order`.version_id = :versionId
SQL;

        $data = $this->connection->fetchAllAssociative(
            $baseSql,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['order_number'] ?? '',
                $row['email'] ?? '',
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['company'] ?? '',
                $row['user_number'] ?? '',
                $row['tags'] ?? '',
                $row['country'] ?? '',
                $row['city'] ?? '',
                $row['street'] ?? '',
                $row['zipcode'] ?? '',
                $row['phone_number'] ?? '',
                $row['additional_address_line1'] ?? '',
                $row['additional_address_line2'] ?? '',
                $row['documentNumber'] ?? '',
                $row['amount_total'] ?? '',
                $row['tracking_codes'] ?? '',
                $id,
            ]));

            $completion = $this->buildCompletion([
                \is_string($row['order_number'] ?? null) ? $row['order_number'] : null,
                \is_string($row['email'] ?? null) ? $row['email'] : null,
                \is_string($row['first_name'] ?? null) ? $row['first_name'] : null,
                \is_string($row['last_name'] ?? null) ? $row['last_name'] : null,
                \is_string($row['company'] ?? null) ? $row['company'] : null,
            ]);

            if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
                $mapped[$id] = [
                    'id' => $id,
                    'text' => \strtolower($text),
                    'completion' => $completion,
                ];

                continue;
            }

            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'completion' => $completion,
                'orderNumber' => $row['order_number'] ?? null,
                'amountTotal' => isset($row['amount_total']) ? (float) $row['amount_total'] : null,
                'orderDate' => $this->formatDateTime($row, 'order_date_time'),
                'orderDateTime' => $this->formatDateTime($row, 'order_date_time'),
                'stateId' => $row['stateId'] ?? null,
                'stateMachineState' => isset($row['stateId']) ? ['id' => $row['stateId'], '_count' => 1] : null,
                'channelId' => $row['channelId'] ?? null,
                'affiliateCode' => $row['affiliateCode'] ?? null,
                'campaignCode' => $row['campaignCode'] ?? null,
                'tags' => $this->parseTagIds($row),
                'billingAddress' => $this->parseAddress($row, 'billingAddressId', 'billingAddressCountryId'),
                'orderUser' => $this->parseOrderUser($row),
                'lineItems' => $this->parseLineItems($row),
                'primaryOrderTransaction' => $this->parsePrimaryOrderTransaction($row),
                'primaryOrderDelivery' => $this->parsePrimaryOrderDelivery($row),
                'documents' => $this->parseTagIds($row, 'documentIds'),
                'createdAt' => $this->formatDateTime($row, 'createdAt'),
            ];
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, countryId: string}|null
     */
    private function parseAddress(array $row, string $idKey, string $countryIdKey): ?array
    {
        if (!isset($row[$idKey]) || $row[$idKey] === '') {
            return null;
        }

        return [
            'id' => $row[$idKey],
            '_count' => 1,
            'countryId' => $row[$countryIdKey] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, user: array{id: string, _count: int, groupId: string, userNumber: string}|null}|null
     */
    private function parseOrderUser(array $row): ?array
    {
        if (!isset($row['orderUserId']) || $row['orderUserId'] === '') {
            return null;
        }

        $user = null;
        if (isset($row['userId']) && $row['userId'] !== '') {
            $user = [
                'id' => $row['userId'],
                '_count' => 1,
                'groupId' => $row['userGroupId'] ?? '',
                'userNumber' => $row['liveUserNumber'] ?? '',
            ];
        }

        return [
            'id' => $row['orderUserId'],
            '_count' => 1,
            'user' => $user,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return list<array{id: string, _count: int, blogId: string|null, payload: array{code: string|null}}>
     */
    private function parseLineItems(array $row): array
    {
        return array_values(array_map(static function (array $item) {
            return [
                'id' => (string) ($item['id'] ?? ''),
                '_count' => 1,
                'blogId' => \is_string($item['blogId']) ? $item['blogId'] : null,
                'payload' => ['code' => \is_string($item['code']) ? $item['code'] : null],
            ];
        }, ElasticsearchIndexingUtils::parseJson($row, 'lineItems')));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, stateMachineState: array{id: string, _count: int}, paymentMethodId: string}|null
     */
    private function parsePrimaryOrderTransaction(array $row): ?array
    {
        if (!isset($row['primaryTransactionId']) || $row['primaryTransactionId'] === '') {
            return null;
        }

        return [
            'id' => $row['primaryTransactionId'],
            '_count' => 1,
            'stateMachineState' => [
                'id' => $row['primaryTransactionStateId'] ?? '',
                '_count' => 1,
            ],
            'paymentMethodId' => $row['primaryTransactionPaymentMethodId'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, _count: int, stateMachineState: array{id: string, _count: int}, shippingMethodId: string, shippingOrderAddress: array{id: string, _count: int, countryId: string}}|null
     */
    private function parsePrimaryOrderDelivery(array $row): ?array
    {
        if (!isset($row['primaryDeliveryId']) || $row['primaryDeliveryId'] === '') {
            return null;
        }

        return [
            'id' => $row['primaryDeliveryId'],
            '_count' => 1,
            'stateMachineState' => [
                'id' => $row['primaryDeliveryStateId'] ?? '',
                '_count' => 1,
            ],
            'shippingMethodId' => $row['primaryDeliveryShippingMethodId'] ?? '',
            'shippingOrderAddress' => [
                'id' => $row['primaryDeliveryId'],
                '_count' => 1,
                'countryId' => $row['primaryDeliveryCountryId'] ?? '',
            ],
        ];
    }
}

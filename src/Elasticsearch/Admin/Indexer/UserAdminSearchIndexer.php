<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Aggregate\UserTag\UserTagDefinition;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;

final class UserAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $repository
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
        return UserDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'user-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $userIds = $event->getPrimaryKeysWithPropertyChange($this->getEntity(), [
            'firstName',
            'lastName',
            'email',
            'company',
            'userNumber',
            'active',
            'groupId',
            'defaultBillingAddressId',
            'defaultShippingAddressId',
        ]);

        $addresses = $event->getPrimaryKeysWithPropertyChange(UserAddressDefinition::ENTITY_NAME, [
            'firstName',
            'lastName',
            'company',
            'city',
            'street',
            'zipcode',
            'phoneNumber',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'countryId',
        ]);

        if ($addresses !== []) {
            $userIds = array_merge($userIds, $event->getPrimaryKeys($this->getEntity()));
        }

        $tags = $event->getPrimaryKeysWithPropertyChange(UserTagDefinition::ENTITY_NAME, [
            'tagId',
        ]);

        foreach ($tags as $pks) {
            if (isset($pks['userId'])) {
                $userIds[] = $pks['userId'];
            }
        }

        return array_values(array_unique(array_filter($userIds, '\is_string')));
    }

    public function mapping(array $mapping): array
    {
        if (!Feature::isActive('ENABLE_OPENSEARCH_FOR_ADMIN_API')) {
            return parent::mapping($mapping);
        }

        $override = [
            'active' => AbstractElasticsearchDefinition::BOOLEAN_FIELD,
            'email' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'firstName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'lastName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'userNumber' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'company' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'affiliateCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'campaignCode' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'groupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'salutationId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'boundChannelId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'requestedGroupId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'defaultBillingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'defaultShippingAddress' => ElasticsearchFieldBuilder::nested([
                'countryId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            ]),
            'createdAt' => ElasticsearchFieldBuilder::datetime(),
            'tags' => ElasticsearchFieldBuilder::nested(),
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
     *     active?: bool,
     *     email?: mixed,
     *     firstName?: mixed,
     *     lastName?: mixed,
     *     userNumber?: mixed,
     *     company?: mixed,
     *     affiliateCode?: mixed,
     *     campaignCode?: mixed,
     *     groupId?: mixed,
     *     salutationId?: mixed,
     *     boundChannelId?: mixed,
     *     requestedGroupId?: mixed,
     *     defaultBillingAddress?: array{
     *         id: string,
     *         _count: int,
     *         countryId: string
     *     }|null,
     *     defaultShippingAddress?: array{
     *         id: string,
     *         _count: int,
     *         countryId: string
     *     }|null,
     *     tags?: list<array{
     *         id: string,
     *         _count: int
     *     }>,
     *     createdAt?: string|null
     * }>
     */
    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT LOWER(HEX(user.id)) as id,
                   tag_agg.tags as tags,
                   tag_agg.tagIds as tagIds,
                   address_agg.country as country,
                   address_agg.address_first_name as address_first_name,
                   address_agg.address_last_name as address_last_name,
                   address_agg.address_company as address_company,
                   address_agg.city as city,
                   address_agg.street as street,
                   address_agg.zipcode as zipcode,
                   address_agg.phone_number as phone_number,
                   address_agg.additional_address_line1 as additional_address_line1,
                   address_agg.additional_address_line2 as additional_address_line2,
                   user.first_name,
                   user.last_name,
                   user.email,
                   user.company,
                   user.user_number,
                   user.active AS active,
                   user.affiliate_code AS affiliateCode,
                   user.campaign_code AS campaignCode,
                   LOWER(HEX(user.user_group_id)) AS groupId,
                   LOWER(HEX(user.salutation_id)) AS salutationId,
                   LOWER(HEX(user.bound_channel_id)) AS boundChannelId,
                   LOWER(HEX(user.requested_customer_group_id)) AS requestedGroupId,
                   LOWER(HEX(user.default_billing_address_id)) AS defaultBillingAddressId,
                   LOWER(HEX(default_billing_address.country_id)) AS defaultBillingAddressCountryId,
                   LOWER(HEX(user.default_shipping_address_id)) AS defaultShippingAddressId,
                   LOWER(HEX(default_shipping_address.country_id)) AS defaultShippingAddressCountryId,
                   user.created_at as createdAt
            FROM user
                LEFT JOIN (
                    SELECT user_address.user_id,
                           GROUP_CONCAT(DISTINCT country_translation.name ORDER BY NULL SEPARATOR ' ') as country,
                           GROUP_CONCAT(DISTINCT user_address.first_name ORDER BY NULL SEPARATOR ' ') as address_first_name,
                           GROUP_CONCAT(DISTINCT user_address.last_name ORDER BY NULL SEPARATOR ' ') as address_last_name,
                           GROUP_CONCAT(DISTINCT user_address.company ORDER BY NULL SEPARATOR ' ') as address_company,
                           GROUP_CONCAT(DISTINCT user_address.city ORDER BY NULL SEPARATOR ' ') as city,
                           GROUP_CONCAT(DISTINCT user_address.street ORDER BY NULL SEPARATOR ' ') as street,
                           GROUP_CONCAT(DISTINCT user_address.zipcode ORDER BY NULL SEPARATOR ' ') as zipcode,
                           GROUP_CONCAT(DISTINCT user_address.phone_number ORDER BY NULL SEPARATOR ' ') as phone_number,
                           GROUP_CONCAT(DISTINCT user_address.additional_address_line1 ORDER BY NULL SEPARATOR ' ') as additional_address_line1,
                           GROUP_CONCAT(DISTINCT user_address.additional_address_line2 ORDER BY NULL SEPARATOR ' ') as additional_address_line2
                    FROM user_address
                    LEFT JOIN country
                        ON user_address.country_id = country.id
                    LEFT JOIN country_translation
                        ON country.id = country_translation.country_id
                    WHERE user_address.user_id IN (:ids)
                    GROUP BY user_address.user_id
                ) as address_agg
                    ON address_agg.user_id = user.id
                LEFT JOIN user_address AS default_billing_address
                    ON user.default_billing_address_id = default_billing_address.id
                LEFT JOIN user_address AS default_shipping_address
                    ON user.default_shipping_address_id = default_shipping_address.id
                LEFT JOIN (
                    SELECT user_tag.user_id,
                           GROUP_CONCAT(DISTINCT tag.name ORDER BY NULL SEPARATOR ' ') as tags,
                           GROUP_CONCAT(LOWER(HEX(tag.id)) ORDER BY NULL SEPARATOR ' ') as tagIds
                    FROM user_tag
                    LEFT JOIN tag
                        ON user_tag.tag_id = tag.id
                    WHERE user_tag.user_id IN (:ids)
                    GROUP BY user_tag.user_id
                ) as tag_agg
                    ON tag_agg.user_id = user.id
            WHERE user.id IN (:ids)
SQL,
            [
                'ids' => Uuid::fromHexToBytesList($ids),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        $mapped = [];
        foreach ($data as $row) {
            $id = (string) $row['id'];
            $text = \implode(' ', array_filter([
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $row['email'] ?? '',
                $row['user_number'] ?? '',
                $row['company'] ?? '',
                $row['tags'] ?? '',
                $row['country'] ?? '',
                $row['address_first_name'] ?? '',
                $row['address_last_name'] ?? '',
                $row['address_company'] ?? '',
                $row['city'] ?? '',
                $row['street'] ?? '',
                $row['zipcode'] ?? '',
                $row['phone_number'] ?? '',
                $row['additional_address_line1'] ?? '',
                $row['additional_address_line2'] ?? '',
                $id,
            ]));

            $completion = $this->buildCompletion([
                \is_string($row['first_name'] ?? null) ? $row['first_name'] : null,
                \is_string($row['last_name'] ?? null) ? $row['last_name'] : null,
                \is_string($row['email'] ?? null) ? $row['email'] : null,
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
                'active' => (bool) $row['active'],
                'email' => $row['email'] ?? null,
                'firstName' => $row['first_name'] ?? null,
                'lastName' => $row['last_name'] ?? null,
                'userNumber' => $row['user_number'] ?? null,
                'company' => $row['company'] ?? null,
                'affiliateCode' => $row['affiliateCode'] ?? null,
                'campaignCode' => $row['campaignCode'] ?? null,
                'groupId' => $row['groupId'] ?? null,
                'salutationId' => $row['salutationId'] ?? null,
                'boundChannelId' => $row['boundChannelId'] ?? null,
                'requestedGroupId' => $row['requestedGroupId'] ?? null,
                'defaultBillingAddress' => $this->parseAddress($row, 'defaultBillingAddressId', 'defaultBillingAddressCountryId'),
                'defaultShippingAddress' => $this->parseAddress($row, 'defaultShippingAddressId', 'defaultShippingAddressCountryId'),
                'tags' => $this->parseTagIds($row),
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
}

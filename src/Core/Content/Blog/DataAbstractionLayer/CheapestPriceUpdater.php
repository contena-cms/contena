<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\Json;
use Contena\Core\Framework\Uuid\Uuid;

class CheapestPriceUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly AbstractCheapestPriceQuantitySelector $quantitySelector,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    /**
     * @param array<string> $parentIds
     */
    public function update(array $parentIds, Context $context): void
    {
        $parentIds = array_unique(array_filter($parentIds));

        if ($parentIds === []) {
            return;
        }

        $all = $this->fetchPrices($parentIds, $context);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        $cheapestPrice = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE blog SET cheapest_price = :price WHERE id = :id AND version_id = :version')
        );

        $accessorQuery = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE blog SET cheapest_price_accessor = :accessor WHERE id = :id AND version_id = :version')
        );

        // Pre-load the existing cheapest-price accessors of all variants for every parent in a single
        // query, keyed by parent id, instead of issuing one SELECT per parent inside the loop below.
        $existingAccessorsByParent = [];
        if ($all !== []) {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT LOWER(HEX(`parent_id`)) AS parentId, `id` AS id, `cheapest_price_accessor` AS accessor
                 FROM blog
                 WHERE `parent_id` IN (:ids) AND `version_id` = :version',
                ['ids' => Uuid::fromHexToBytesList(array_keys($all)), 'version' => $versionId],
                ['ids' => ArrayParameterType::BINARY]
            );

            foreach ($rows as $row) {
                $existingAccessorsByParent[$row['parentId']][$row['id']] = $row['accessor'];
            }
        }

        $variantIdsUpdated = [];

        foreach ($all as $blogId => $prices) {
            $container = new CheapestPriceContainer($prices);

            $cheapestPrice->execute([
                'price' => serialize($container),
                'id' => Uuid::fromHexToBytes($blogId),
                'version' => $versionId,
            ]);

            $variantIds = $container->getVariantIds();

            if (!$variantIds) {
                continue;
            }

            $existingAccessors = $existingAccessorsByParent[$blogId] ?? [];

            foreach ($container->getVariantIds() as $variantId) {
                $accessor = Json::encode($this->buildAccessor($container, $variantId));

                if (($existingAccessors[Uuid::fromHexToBytes($variantId)] ?? null) === $accessor) {
                    continue;
                }

                if ($variantId !== $blogId) {
                    $variantIdsUpdated[] = $variantId;
                }

                $accessorQuery->execute([
                    'accessor' => $accessor,
                    'id' => Uuid::fromHexToBytes($variantId),
                    'version' => $versionId,
                ]);
            }
        }

        if ($variantIdsUpdated !== []) {
            $this->dispatcher->dispatch(new BlogIndexerEvent($variantIdsUpdated, $context, ['blog.seo-url']));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccessor(CheapestPriceContainer $container, string $variantId): array
    {
        $rules = $container->getRuleIds();
        $rules[] = 'default';

        $variantPrices = $container->getPricesForVariant($variantId);
        $formattedPrices = [];
        foreach ($rules as $ruleId) {
            $cheapest = $this->getCheapest($ruleId, $variantPrices, $container->getDefault());

            if ($cheapest === null) {
                continue;
            }
            $mapped = [];
            foreach ($cheapest['price'] as $price) {
                $mapped['currency' . $price['currencyId']] = $this->mapPrice($price);
            }

            $formattedPrices['rule' . $ruleId] = $mapped;
        }

        return $formattedPrices;
    }

    /**
     * @param array<string, mixed> $prices
     * @param array<string, mixed>|null $default
     *
     * @return array<string, mixed>|null
     */
    private function getCheapest(?string $ruleId, array $prices, ?array $default): ?array
    {
        if ($ruleId !== null && isset($prices[$ruleId])) {
            return $prices[$ruleId];
        }

        if ($ruleId === 'default') {
            return $default;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $price
     *
     * @return array<string, mixed>
     */
    private function mapPrice(array $price): array
    {
        $array = ['gross' => $price['gross'], 'net' => $price['net']];

        if (isset($price['listPrice'])) {
            $array['listPrice'] = [
                'gross' => $price['listPrice']['gross'],
                'net' => $price['listPrice']['net'],
            ];
        }

        if (isset($price['percentage'])) {
            $array['percentage'] = [
                'gross' => $price['percentage']['gross'],
                'net' => $price['percentage']['net'],
            ];
        }

        return $array;
    }

    /**
     * @param array<string> $ids
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function fetchPrices(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(IFNULL(blog.parent_id, blog.id))) as parent_id',
            'LOWER(HEX(blog.id)) as variant_id',
            'LOWER(HEX(price.rule_id)) as rule_id',
            'LOWER(HEX(IFNULL(blog.unit_id, parent.unit_id))) as unit_id',
            'IFNULL(blog.purchase_unit, parent.purchase_unit) as purchase_unit',
            'IFNULL(blog.reference_unit, parent.reference_unit) as reference_unit',
            'IFNULL(blog.min_purchase, parent.min_purchase) as min_purchase',
            'price.price',
        );

        $query->from('blog', 'blog');
        $query->innerJoin('blog', 'blog_price', 'price', 'price.blog_id = blog.prices AND blog.version_id = price.blog_version_id');
        $query->leftJoin('blog', 'blog', 'parent', 'parent.id = blog.parent_id');

        $query->andWhere('blog.id IN (:ids) OR blog.parent_id IN (:ids)');
        $query->andWhere('blog.version_id = :version');
        $query->andWhere('IFNULL(blog.active, parent.active) = 1');
        $query->andWhere('(blog.child_count = 0 OR blog.parent_id IS NOT NULL)');

        $this->quantitySelector->add($query);

        $idsBytes = Uuid::fromHexToBytesList($ids);
        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $query->setParameter('ids', $idsBytes, ArrayParameterType::BINARY);
        $query->setParameter('version', $versionBytes);

        $data = $query->executeQuery()->fetchAllAssociative();

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(IFNULL(blog.parent_id, blog.id))) as parent_id',
            'LOWER(HEX(blog.id)) as variant_id',
            'NULL as rule_id',
            '0 AS is_ranged',
            'blog.price as price',
            'IFNULL(blog.min_purchase, parent.min_purchase) as min_purchase',
            'LOWER(HEX(IFNULL(blog.unit_id, parent.unit_id))) as unit_id',
            'IFNULL(blog.purchase_unit, parent.purchase_unit) as purchase_unit',
            'IFNULL(blog.reference_unit, parent.reference_unit) as reference_unit',
            'blog.child_count as child_count',
        );

        $query->from('blog', 'blog');
        $query->leftJoin('blog', 'blog', 'parent', 'blog.parent_id = parent.id');
        $query->andWhere('blog.id IN (:ids) OR blog.parent_id IN (:ids)');
        $query->andWhere('blog.version_id = :version');
        $query->andWhere('IFNULL(blog.active, parent.active) = 1 OR blog.child_count > 0'); // always load parent blogs
        $query->setParameter('ids', $idsBytes, ArrayParameterType::BINARY);
        $query->setParameter('version', $versionBytes);

        $defaults = $query->executeQuery()->fetchAllAssociative();

        // Collect all unique blog IDs from both queries
        $blogIds = [];
        $rows = [...$data, ...$defaults];
        foreach ($rows as $row) {
            $blogIds[$row['variant_id']] = true;
        }

        // Fetch visibility once for all blogs
        $visibilityMap = $this->fetchVisibilityMap(array_keys($blogIds), $context);

        $grouped = [];
        foreach ($data as $row) {
            $row['price'] = json_decode((string) $row['price'], true, 512, \JSON_THROW_ON_ERROR);
            $row['channel_ids'] = $visibilityMap[$row['variant_id']] ?? $visibilityMap[$row['parent_id']] ?? [];
            $grouped[(string) $row['parent_id']][(string) $row['variant_id']][(string) $row['rule_id']] = $row;
        }

        foreach ($defaults as $row) {
            if ($row['price'] === null) {
                $grouped[(string) $row['parent_id']][(string) $row['variant_id']]['default'] = null;

                continue;
            }

            $row['price'] = json_decode((string) $row['price'], true, 512, \JSON_THROW_ON_ERROR);
            $row['price'] = $this->normalizePrices($row['price']);
            $row['channel_ids'] = $visibilityMap[$row['variant_id']] ?? $visibilityMap[$row['parent_id']] ?? [];

            if ($row['child_count'] > 0) {
                $grouped[(string) $row['parent_id']]['default'] = $row;

                continue;
            }

            $grouped[(string) $row['parent_id']][(string) $row['variant_id']]['default'] = $row;
        }

        return $grouped;
    }

    /**
     * @param array<string> $blogIds
     *
     * @return array<string, array<string>> Map of blog_id => [channel_ids]
     */
    private function fetchVisibilityMap(array $blogIds, Context $context): array
    {
        if ($blogIds === []) {
            return [];
        }

        $results = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(blog_id)) as blog_id, LOWER(HEX(channel_id)) as channel_id
             FROM blog_visibility
             WHERE blog_id IN (:ids)
             AND blog_version_id = :version',
            [
                'ids' => Uuid::fromHexToBytesList($blogIds),
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ],
            [
                'ids' => ArrayParameterType::BINARY,
            ]
        );

        return FetchModeHelper::group($results, static fn ($row): string => (string) $row['channel_id']);
    }

    /**
     * @param array<string, mixed> $prices
     *
     * @return array<string, mixed>
     */
    private function normalizePrices(array $prices): array
    {
        foreach ($prices as &$price) {
            $price['net'] = (float) $price['net'];
            $price['gross'] = (float) $price['gross'];
        }

        return $prices;
    }
}

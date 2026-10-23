<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer\VariantListingUpdaterTest
 */
class VariantListingUpdater
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string> $ids
     *
     * @throws Exception
     */
    public function update(array $ids, Context $context): void
    {
        $ids = array_filter($ids);

        if ($ids === []) {
            return;
        }

        $ids = array_keys(array_flip($ids));

        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $listingConfiguration = $this->getListingConfiguration($ids, $context);

        $displayParent = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE blog SET display_group = SHA2(HEX(blog.id), 256) WHERE blog.id = :id AND blog.version_id = :versionId')
        );

        $hideParent = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE blog SET display_group = NULL WHERE blog.id = :id AND blog.version_id = :versionId')
        );

        $singleVariant = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE blog SET display_group = SHA2(HEX(blog.parent_id), 256) WHERE blog.parent_id = :id AND blog.version_id = :versionId')
        );

        foreach ($listingConfiguration as $parentId => $config) {
            $childCount = (int) $config['child_count'];
            $groups = $config['groups'];

            if ($config['main_variant'] || $config['display_parent']) {
                $groups = [];
            }

            if ($childCount <= 0) {
                // display parent in listing
                $displayParent->execute(['id' => $parentId, 'versionId' => $versionBytes]);
            } else {
                // hide parent
                $hideParent->execute(['id' => $parentId, 'versionId' => $versionBytes]);
            }

            if ($groups === []) {
                // display single variant in listing
                $singleVariant->execute(['id' => $parentId, 'versionId' => $versionBytes]);

                continue;
            }

            $query = $this->connection->createQueryBuilder();

            $query->from('(SELECT 1)', 'root');

            $fields = [];
            $params = ['parentId' => $parentId, 'versionId' => $versionBytes];
            // Positional index keeps SQL aliases and Doctrine parameter names unique and stable.
            foreach ($groups as $index => $groupId) {
                $mappingAlias = 'mapping' . $index;
                $optionAlias = 'option' . $index;

                $query->innerJoin('root', 'blog_option', $mappingAlias, $mappingAlias . '.blog_id IS NOT NULL');
                $query->innerJoin($mappingAlias, 'property_group_option', $optionAlias, $optionAlias . '.id = ' . $mappingAlias . '.property_group_option_id AND ' . $optionAlias . '.property_group_id = :' . $optionAlias);
                $query->andWhere($mappingAlias . '.blog_id = blog.id AND ' . $mappingAlias . '.blog_version_id = :versionId');

                $fields[] = 'LOWER(HEX(' . $optionAlias . '.id))';

                $params[$optionAlias] = Uuid::fromHexToBytes($groupId);
            }

            $query->addSelect('CONCAT(' . implode(',', $fields) . ')');

            $sql = '
            UPDATE blog SET display_group = SHA2(
                CONCAT(
                    LOWER(HEX(blog.parent_id)),
                    (' . $query->getSQL() . ')
                ),
                256
            ) WHERE parent_id = :parentId AND version_id = :versionId';

            RetryableQuery::retryable($this->connection, function () use ($sql, $params): void {
                $this->connection->executeStatement($sql, $params);
            });
        }
    }

    /**
     * @param array<string> $ids
     *
     * @throws Exception
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function getListingConfiguration(array $ids, Context $context): array
    {
        $versionBytes = Uuid::fromHexToBytes($context->getVersionId());

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'blog.id as id',
            'blog.variant_listing_config as config',
            '(SELECT COUNT(id) FROM blog as child WHERE blog.id = child.parent_id) as child_count',
        );
        $query->from('blog');
        $query->andWhere('blog.version_id = :version');
        $query->andWhere('blog.id IN (:ids)');
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::BINARY);
        $query->setParameter('version', $versionBytes);

        $configuration = $query->executeQuery()->fetchAllAssociative();

        $listingConfiguration = [];
        foreach ($configuration as $row) {
            $decodedConfig = $this->decodeVariantListingConfig($row['config'] ?? null);

            $groups = [];
            $groupConfig = $decodedConfig['configuratorGroupConfig'] ?? [];
            if (\is_array($groupConfig)) {
                foreach ($groupConfig as $group) {
                    if (!\is_array($group)
                        || !\array_key_exists('expressionForListings', $group)
                        || $group['expressionForListings'] !== true
                        || !\is_string($group['id'])) {
                        continue;
                    }

                    $groupId = strtolower($group['id']);
                    if (!Uuid::isValid($groupId)) {
                        continue;
                    }

                    $groups[] = $groupId;
                }
            }

            $listingConfiguration[$row['id']] = [
                'groups' => $groups,
                'child_count' => $row['child_count'] ?? null,
                'main_variant' => $decodedConfig['mainVariantId'] ?? null,
                'display_parent' => $decodedConfig['displayParent'] ?? null,
            ];
        }

        return $listingConfiguration;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeVariantListingConfig(mixed $raw): array
    {
        if (!\is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return \is_array($decoded) ? $decoded : [];
    }
}

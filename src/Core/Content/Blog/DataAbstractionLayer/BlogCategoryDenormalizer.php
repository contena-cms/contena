<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableTransaction;
use Contena\Core\Framework\DataAbstractionLayer\Util\StatementHelper;
use Contena\Core\Framework\Uuid\Uuid;

class BlogCategoryDenormalizer
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<int, string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        $ids = array_unique(\array_filter($ids));
        $allIds = [];

        if ($ids === []) {
            return;
        }

        $categories = $this->fetchMapping($ids, $context);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $tenantId = $context->getTenantId();
        $tenantId = $tenantId !== null ? Uuid::fromHexToBytes($tenantId) : null;

        $inserts = [];
        $updates = [];
        foreach ($categories as $blogId => $mapping) {
            $blogId = Uuid::fromHexToBytes($blogId);
            $allIds[] = $blogId;
            $categoryIds = $this->mapCategories($mapping);

            $json = null;
            if ($categoryIds !== []) {
                $json = json_encode($categoryIds, \JSON_THROW_ON_ERROR);
            }

            $updates[] = ['id' => $blogId, 'tree' => $json, 'version' => $versionId];

            if ($categoryIds === []) {
                continue;
            }

            foreach ($categoryIds as $id) {
                $inserts[] = [
                    'tenant_id' => $tenantId,
                    'blog_id' => $blogId,
                    'blog_version_id' => $versionId,
                    'category_id' => Uuid::fromHexToBytes($id),
                    'category_version_id' => $liveVersionId,
                ];
            }
        }

        RetryableTransaction::retryable($this->connection, function () use ($allIds, $versionId): void {
            $this->connection->executeStatement(
                'DELETE FROM blog_category_tree WHERE `blog_id` IN (:ids) AND `blog_version_id` = :version',
                ['ids' => $allIds, 'version' => $versionId],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        RetryableTransaction::retryable($this->connection, function () use ($updates): void {
            $query = $this->connection->prepare('UPDATE blog SET category_tree = :tree WHERE id = :id AND version_id = :version');

            foreach ($updates as $update) {
                StatementHelper::executeStatement($query, $update);
            }
        });

        $this->insertTree($inserts);
    }

    /**
     * @param array<array<string, string|null>> $inserts
     */
    private function insertTree(array $inserts): void
    {
        if ($inserts === []) {
            return;
        }

        $queue = new MultiInsertQueryQueue($this->connection, 250, true);
        foreach ($inserts as $insert) {
            $queue->addInsert('blog_category_tree', $insert);
        }
        $queue->execute();
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<string, array<string, string>>
     */
    private function fetchMapping(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(blog.id)) as blog_id',
            'GROUP_CONCAT(category.path SEPARATOR \'\') as paths',
            'GROUP_CONCAT(LOWER(HEX(category.id)) SEPARATOR \'|\') as ids',
        );
        $query->from('blog');
        $query->leftJoin(
            'blog',
            'blog_category',
            'mapping',
            'mapping.blog_id = blog.id AND mapping.blog_version_id = blog.version_id'
        );
        $query->leftJoin(
            'mapping',
            'category',
            'category',
            'mapping.category_id = category.id AND mapping.category_version_id = category.version_id AND mapping.category_version_id = :live'
        );

        $query->addGroupBy('blog.id');

        $query->andWhere('blog.id IN (:ids)');
        $query->andWhere('blog.version_id = :version');

        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));
        $query->setParameter('live', Uuid::fromHexToBytes(Defaults::LIVE_VERSION));

        $bytes = array_map(static fn (string $id) => Uuid::fromHexToBytes($id), $ids);

        $query->setParameter('ids', $bytes, ArrayParameterType::BINARY);

        $rows = $query->executeQuery()->fetchAllAssociative();

        /** @var array<string, array<string, string>> $unique */
        $unique = FetchModeHelper::groupUnique($rows);

        return $unique;
    }

    /**
     * @param array<string, string|null> $mapping
     *
     * @return array<int, string>
     */
    private function mapCategories(array $mapping): array
    {
        $categoryIds = array_filter(explode('|', (string) $mapping['ids']));
        $categoryIds = array_merge(
            explode('|', (string) $mapping['paths']),
            $categoryIds
        );

        $categoryIds = array_map('strtolower', $categoryIds);

        return array_keys(array_flip(array_filter($categoryIds)));
    }
}

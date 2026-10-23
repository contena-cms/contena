<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Uuid\Uuid;

class RatingAverageUpdater
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string> $ids
     */
    public function update(array $ids, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        RetryableQuery::retryable($this->connection, function () use ($ids, $versionId): void {
            $this->connection->executeStatement(
                'UPDATE blog SET rating_average = NULL WHERE (parent_id IN (:ids) OR id IN (:ids)) AND version_id = :version',
                ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $versionId],
                ['ids' => ArrayParameterType::BINARY]
            );
        });

        $query = $this->connection->createQueryBuilder();
        $query->select(
            'IFNULL(blog.parent_id, blog.id) as id',
            'AVG(NULLIF(blog_review.points, 0)) as average',
        );
        $query->from('blog_review');
        $query->innerJoin('blog_review', 'blog', 'blog', 'blog.id = blog_review.blog_id');
        $query->andWhere('blog_review.status = 1');
        $query->andWhere('blog.id IN (:ids) OR blog.parent_id IN (:ids)');
        $query->andWhere('blog.version_id = :version');
        $query->setParameter('version', $versionId);
        $query->setParameter('ids', Uuid::fromHexToBytesList($ids), ArrayParameterType::BINARY);
        $query->addGroupBy('IFNULL(blog.parent_id, blog.id)');

        $averages = $query->executeQuery()->fetchAllAssociative();

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE blog SET rating_average = :average WHERE id = :id AND version_id = :version')
        );

        foreach ($averages as $average) {
            $query->execute([
                'average' => $average['average'],
                'id' => $average['id'],
                'version' => $versionId,
            ]);
        }
    }
}

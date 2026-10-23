<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamTranslation\BlogStreamTranslationDefinition;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;

final class BlogStreamAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogStreamCollection> $repository
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
        return BlogStreamDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'blog-stream-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $ids = [];

        $translations = $event->getPrimaryKeysWithPropertyChange(BlogStreamTranslationDefinition::ENTITY_NAME, [
            'name',
        ]);

        foreach ($translations as $pks) {
            if (isset($pks['blogStreamId'])) {
                $ids[] = $pks['blogStreamId'];
            }
        }

        return array_values(array_unique(array_filter($ids, '\is_string')));
    }

    public function globalData(array $result, Context $context): array
    {
        $ids = array_column($result['hits'], 'id');

        return [
            'total' => (int) $result['total'],
            'data' => $this->repository->search(new Criteria($ids), $context)->getEntities(),
        ];
    }

    public function fetch(array $ids): array
    {
        $data = $this->connection->fetchAllAssociative(
            '
            SELECT LOWER(HEX(blog_stream.id)) as id,
                   GROUP_CONCAT(DISTINCT blog_stream_translation.name SEPARATOR " ") as name
            FROM blog_stream
                INNER JOIN blog_stream_translation
                    ON blog_stream.id = blog_stream_translation.blog_stream_id
            WHERE blog_stream.id IN (:ids)
            GROUP BY blog_stream.id
        ',
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
            $text = \implode(' ', array_filter(array_unique(array_values($row))));
            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'completion' => $this->buildCompletion([\is_string($row['name'] ?? null) ? $row['name'] : null]),
            ];
        }

        return $mapped;
    }
}

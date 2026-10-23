<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroupTranslation\UserGroupTranslationDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

final class UserGroupAdminSearchIndexer extends AbstractAdminIndexer
{
    /**
     * @internal
     *
     * @param EntityRepository<UserGroupCollection> $repository
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
        return UserGroupDefinition::ENTITY_NAME;
    }

    public function getName(): string
    {
        return 'user-group-listing';
    }

    public function getIterator(): IterableQuery
    {
        return $this->factory->createIterator($this->getEntity(), null, $this->indexingBatchSize);
    }

    public function getUpdatedIds(EntityWrittenContainerEvent $event): array
    {
        $ids = [];

        $translations = $event->getPrimaryKeysWithPropertyChange(UserGroupTranslationDefinition::ENTITY_NAME, [
            'name',
        ]);

        foreach ($translations as $pks) {
            if (isset($pks['userGroupId'])) {
                $ids[] = $pks['userGroupId'];
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
            SELECT LOWER(HEX(user_group.id)) as id,
                   GROUP_CONCAT(DISTINCT user_group_translation.name SEPARATOR " ") as name
            FROM user_group
                INNER JOIN user_group_translation
                    ON user_group.id = user_group_translation.user_group_id
            WHERE user_group.id IN (:ids)
            GROUP BY user_group.id
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
            $text = \implode(' ', array_filter($row));
            $mapped[$id] = [
                'id' => $id,
                'text' => \strtolower($text),
                'completion' => $this->buildCompletion([\is_string($row['name'] ?? null) ? $row['name'] : null]),
            ];
        }

        return $mapped;
    }
}

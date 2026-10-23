<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Blog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class BlogCustomFieldsUsedUpdater implements EventSubscriberInterface
{
    public function __construct(
        private readonly ElasticsearchHelper $elasticsearchHelper,
        private readonly ElasticsearchCustomFieldsMappingHelper $mappingHelper,
        private readonly Connection $connection
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BlogSortingDefinition::ENTITY_NAME . '.written' => 'blogSortingWritten',
            ContentStreamFilterDefinition::ENTITY_NAME . '.written' => 'contentStreamFilterWritten',
        ];
    }

    public function blogSortingWritten(EntityWrittenEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $blogSortingIds = [];

        foreach ($event->getResults()->withPayloadProperties('fields') as $writeResult) {
            $key = $writeResult->getPrimaryKey();
            if (!\is_string($key)) {
                continue;
            }

            $blogSortingIds[] = $key;
        }

        if ($blogSortingIds === []) {
            return;
        }

        $customFieldNames = $this->fetchCustomFieldNamesFromSortings($blogSortingIds);

        $this->createFieldsInIndices($customFieldNames);
    }

    public function contentStreamFilterWritten(EntityWrittenEvent $event): void
    {
        if (!$this->elasticsearchHelper->allowIndexing()) {
            return;
        }

        $contentStreamFilterIds = [];

        foreach ($event->getResults()->only(
            EntityWriteResult::OPERATION_INSERT,
            EntityWriteResult::OPERATION_UPDATE,
        ) as $writeResult) {
            $key = $writeResult->getPrimaryKey();
            if (!\is_string($key)) {
                continue;
            }

            $contentStreamFilterIds[] = $key;
        }

        if ($contentStreamFilterIds === []) {
            return;
        }

        $customFieldNames = $this->fetchCustomFieldNamesFromStreamFilters($contentStreamFilterIds);

        $this->createFieldsInIndices($customFieldNames);
    }

    /**
     * @param list<string> $blogSortingIds
     *
     * @return list<string>
     */
    private function fetchCustomFieldNamesFromSortings(array $blogSortingIds): array
    {
        $rows = $this->connection->fetchFirstColumn(
            'SELECT fields FROM blog_sorting WHERE id IN (:ids) AND fields LIKE :pattern',
            ['ids' => Uuid::fromHexToBytesList($blogSortingIds), 'pattern' => '%customFields.%'],
            ['ids' => ArrayParameterType::STRING]
        );

        $customFieldNames = [];
        $prefixLength = \strlen('customFields.');

        foreach ($rows as $row) {
            try {
                $fields = json_decode((string) $row, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!\is_array($fields)) {
                continue;
            }

            foreach ($fields as $field) {
                $name = $field['field'] ?? null;

                if (\is_string($name) && str_starts_with($name, 'customFields.')) {
                    $customFieldNames[substr($name, $prefixLength)] = true;
                }
            }
        }

        return array_keys($customFieldNames);
    }

    /**
     * @param list<string> $contentStreamFilterIds
     *
     * @return list<string>
     */
    private function fetchCustomFieldNamesFromStreamFilters(array $contentStreamFilterIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT REPLACE(field, \'customFields.\', \'\') FROM content_stream_filter WHERE id IN (:ids) AND field LIKE :field',
            ['ids' => Uuid::fromHexToBytesList($contentStreamFilterIds), 'field' => 'customFields.%'],
            ['ids' => ArrayParameterType::STRING]
        );
    }

    /**
     * @param list<string> $customFieldNames
     */
    private function createFieldsInIndices(array $customFieldNames): void
    {
        $customFieldNames = array_values(array_unique($customFieldNames));

        $customFieldTypes = $this->fetchCustomFieldTypesByName($customFieldNames);

        $fields = ElasticsearchCustomFieldsMappingHelper::mapCustomFieldsToEsTypes($customFieldTypes);

        $this->mappingHelper->createFieldsInIndices($fields);
    }

    /**
     * @param array<string> $fieldNames
     *
     * @return array<string, string>
     */
    private function fetchCustomFieldTypesByName(array $fieldNames): array
    {
        if ($fieldNames === []) {
            return [];
        }

        $result = $this->connection->fetchAllKeyValue(
            'SELECT name, type FROM custom_field WHERE name IN (:names)',
            ['names' => $fieldNames],
            ['names' => ArrayParameterType::STRING]
        );

        return $result;
    }
}

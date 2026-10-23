<?php declare(strict_types=1);

namespace Contena\Core\Content\BlogStream\DataAbstractionLayer;

use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;

final class BlogStreamWriteResultHelper
{
    /**
     * @return list<string>
     */
    public static function getAffectedStreamIds(EntityWrittenContainerEvent $event): array
    {
        return self::getAffectedStreamIdsFromEvent(
            $event->getEventByEntityName(BlogStreamFilterDefinition::ENTITY_NAME)
        );
    }

    /**
     * @return list<string>
     */
    public static function getAffectedStreamIdsFromEvent(?EntityWrittenEvent $event): array
    {
        if ($event === null) {
            return [];
        }

        $streamIds = [];

        foreach ($event->getWriteResults() as $writeResult) {
            foreach (self::collectStreamIds($writeResult) as $streamId) {
                $streamIds[$streamId] = true;
            }
        }

        return array_keys($streamIds);
    }

    /**
     * @return list<string>
     */
    private static function collectStreamIds(EntityWriteResult $writeResult): array
    {
        $ids = [];

        $payload = $writeResult->getPayload();
        $payloadId = self::normalizeStreamId(
            $payload['blogStreamId'] ?? $payload['blog_stream_id'] ?? null
        );
        if ($payloadId !== null) {
            $ids[] = $payloadId;
        }

        $state = $writeResult->getExistence()?->getState();
        $stateId = self::normalizeStreamId(
            $state['blog_stream_id'] ?? $state['blogStreamId'] ?? null
        );
        if ($stateId !== null) {
            $ids[] = $stateId;
        }

        // deletes carry only the primary key, partial updates only the changed fields; the change set
        // requested by BlogStreamFilterChangeSetSubscriber holds the previous row state
        $beforeId = self::normalizeStreamId(
            $writeResult->getChangeSet()?->getBefore('blog_stream_id')
        );
        if ($beforeId !== null) {
            $ids[] = $beforeId;
        }

        return $ids;
    }

    private static function normalizeStreamId(mixed $streamId): ?string
    {
        if (!\is_string($streamId) || $streamId === '') {
            return null;
        }

        if (Uuid::isValid($streamId)) {
            return $streamId;
        }

        if (\strlen($streamId) === 16) {
            return Uuid::fromBytesToHex($streamId);
        }

        return null;
    }
}

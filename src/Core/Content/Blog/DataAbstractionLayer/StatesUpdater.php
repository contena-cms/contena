<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\Events\BlogStatesBeforeChangeEvent;
use Contena\Core\Content\Blog\Events\BlogStatesChangedEvent;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\State;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @deprecated tag:v6.8.0 - Will be removed, as blog states are deprecated.
 */
class StatesUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, as blog states are deprecated.
     *
     * @param string[] $ids
     */
    public function update(array $ids, Context $context): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, 'update', 'v6.8.0.0')
        );

        $sql = 'SELECT LOWER(HEX(`blog`.`id`)) as id,
                IF(`blog_download`.`id` IS NOT NULL, 1, 0) as hasDownloads,
                `blog`.`type`,
                `blog`.`states`
                FROM `blog`
                LEFT JOIN `blog_download` ON `blog`.`id` = `blog_download`.`blog_id`
                AND `blog`.`version_id` = `blog_download`.`blog_version_id`
                WHERE `blog`.`id` IN (:ids)
                AND `blog`.`version_id` = :versionId
                GROUP BY `blog`.`id`';

        $params = [
            'ids' => Uuid::fromHexToBytesList($ids),
            'versionId' => Uuid::fromHexToBytes($context->getVersionId()),
        ];

        $blogs = $this->connection->fetchAllAssociative(
            $sql,
            $params,
            ['ids' => ArrayParameterType::BINARY]
        );

        $updates = [];
        foreach ($blogs as $blog) {
            // Only update states for physical and digital blogs, as the states are only relevant for these types. For other types, the states will be left unchanged.
            if (isset($blog['type']) && $blog['type'] !== BlogDefinition::TYPE_PHYSICAL && $blog['type'] !== BlogDefinition::TYPE_DIGITAL) {
                continue;
            }

            $newStates = $this->getNewStates($blog);
            $oldStates = $blog['states'] ? json_decode((string) $blog['states'], true, 512, \JSON_THROW_ON_ERROR) : [];

            if (array_diff($newStates, $oldStates) === []) {
                continue;
            }

            $updates[] = new UpdatedStates($blog['id'], $oldStates, $newStates);
        }

        if ($updates === []) {
            return;
        }

        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE `blog` SET `states` = :states, `type` = :type WHERE `id` = :id AND `version_id` = :version')
        );

        $event = new BlogStatesBeforeChangeEvent($updates, $context);
        $this->eventDispatcher->dispatch($event);

        foreach ($event->getUpdatedStates() as $updatedStates) {
            $query->execute([
                'states' => json_encode($updatedStates->getNewStates(), \JSON_THROW_ON_ERROR),
                'type' => \in_array(State::IS_DOWNLOAD, $updatedStates->getNewStates(), true) ? BlogDefinition::TYPE_DIGITAL : BlogDefinition::TYPE_PHYSICAL,
                'id' => Uuid::fromHexToBytes($updatedStates->getId()),
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
            ]);
        }

        $this->eventDispatcher->dispatch(new BlogStatesChangedEvent($event->getUpdatedStates(), $context));
    }

    /**
     * @param mixed[] $blog
     *
     * @return string[]
     */
    private function getNewStates(array $blog): array
    {
        $states = [];

        if ($blog['type'] === BlogDefinition::TYPE_DIGITAL || (int) $blog['hasDownloads'] === 1) {
            $states[] = State::IS_DOWNLOAD;
        } else {
            $states[] = State::IS_PHYSICAL;
        }

        return $states;
    }
}

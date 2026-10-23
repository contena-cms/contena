<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Event\Subscriber;

use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportBatchEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportRecordsEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Contena\Core\Content\ImportExport\ImportExportException;
use Contena\Core\Content\ImportExport\Service\UserNumberRangeConfigService;
use Contena\Core\Content\ImportExport\Service\UserNumberRangePatternMatcher;
use Contena\Core\Content\ImportExport\Struct\ImportResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
final class UserNumberRangeSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly UserNumberRangeConfigService $numberPatternConfigService,
        private readonly UserNumberRangePatternMatcher $numberPatternMatcher,
        private readonly AbstractIncrementStorage $incrementStorage,
        private readonly EntityRepository $userRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ImportExportBeforeImportRecordEvent::class => 'onBeforeImportRecord',
            ImportExportAfterImportBatchEvent::class => 'onAfterImport',
            ImportExportAfterImportRecordsEvent::class => 'onAfterImport',
        ];
    }

    public function onBeforeImportRecord(ImportExportBeforeImportRecordEvent $event): void
    {
        if (!$this->isUserEntity($event)) {
            return;
        }

        $record = $event->getRecord();
        $userNumber = $record['userNumber'] ?? null;
        if (!\is_string($userNumber)) {
            return;
        }

        $channelId = $this->getChannelId($record);
        $patternConfig = $this->numberPatternConfigService->getPatternConfig($channelId);
        if ($patternConfig === null) {
            return;
        }

        if ($this->numberPatternMatcher->extractIncrement($patternConfig['pattern'], $userNumber) === null) {
            throw ImportExportException::processingError(\sprintf(
                'User number "%s" does not match the configured user number pattern.',
                $userNumber,
            ));
        }

        $excludedUserId = null;
        if ($this->isUpdateEvent($event)) {
            $excludedUserId = $this->hasValidUuid($record, 'id') ? $record['id'] : null;
        }

        $criteria = $this->createUserNumberSearchCriteria($userNumber, $excludedUserId);
        $usersSearchResult = $this->userRepository->search($criteria, $event->getContext())->getEntities();

        foreach ($usersSearchResult as $user) {
            if ($excludedUserId !== null && $user->getId() === $excludedUserId) {
                continue;
            }

            $existingConfigurationId = $this->numberPatternConfigService->getPatternConfigId($user->getChannelId());
            if ($existingConfigurationId !== $patternConfig['id']) {
                continue;
            }

            throw ImportExportException::processingError(\sprintf(
                'User number "%s" is already used in the selected number range.',
                $userNumber,
            ));
        }
    }

    public function onAfterImport(ImportExportAfterImportBatchEvent|ImportExportAfterImportRecordsEvent $event): void
    {
        if ($event->getConfig()->get('sourceEntity') !== UserDefinition::ENTITY_NAME) {
            return;
        }

        /** @var array<string, int> $highestIncrements */
        $highestIncrements = [];
        $channelIdsForUpdatedUsers = $this->getChannelIdsForUpdatedUsers($event->getResult(), $event->getContext());

        foreach ($this->userWriteResults($event->getResult()) as $userWriteResult) {
            if (!\in_array($userWriteResult->getOperation(), [
                EntityWriteResult::OPERATION_INSERT,
                EntityWriteResult::OPERATION_UPDATE,
            ], true)) {
                continue;
            }

            $userNumber = $userWriteResult->getProperty('userNumber');
            if (!\is_string($userNumber)) {
                continue;
            }

            $channelId = $userWriteResult->getProperty('channelId');
            if (!\is_string($channelId) && $userWriteResult->getOperation() === EntityWriteResult::OPERATION_UPDATE) {
                $userId = $userWriteResult->getPrimaryKey();
                $channelId = \is_string($userId) ? ($channelIdsForUpdatedUsers[$userId] ?? null) : null;
            }

            if ($userWriteResult->getOperation() === EntityWriteResult::OPERATION_UPDATE && !\is_string($channelId)) {
                continue;
            }

            $channelId = \is_string($channelId) ? $channelId : null;
            $configuration = $this->numberPatternConfigService->getPatternConfig($channelId);
            if ($configuration === null) {
                continue;
            }

            $increment = $this->numberPatternMatcher->extractIncrement($configuration['pattern'], $userNumber);
            if ($increment === null) {
                continue;
            }

            $configurationId = $configuration['id'];
            $highestIncrements[$configurationId] = \max(
                $highestIncrements[$configurationId] ?? 0,
                $increment,
            );
        }

        foreach ($highestIncrements as $configurationId => $increment) {
            $this->incrementStorage->increaseToAtLeast($configurationId, $increment);
        }

        $this->numberPatternConfigService->reset();
    }

    /**
     * @return list<EntityWriteResult>
     */
    private function userWriteResults(ImportResult $result): array
    {
        $userWriteResults = [];

        foreach ($result->results as $writtenContainerEvent) {
            $nestedEvents = $writtenContainerEvent->getEvents();
            if ($nestedEvents === null) {
                continue;
            }

            foreach ($nestedEvents as $nestedEvent) {
                if ($nestedEvent->getEntityName() !== UserDefinition::ENTITY_NAME) {
                    continue;
                }

                foreach ($nestedEvent->getWriteResults() as $writeResult) {
                    $userWriteResults[] = $writeResult;
                }
            }
        }

        return $userWriteResults;
    }

    /**
     * @return array<string, string|null>
     */
    private function getChannelIdsForUpdatedUsers(ImportResult $result, Context $context): array
    {
        $userIds = [];

        foreach ($this->userWriteResults($result) as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $userId = $writeResult->getPrimaryKey();
            if (\is_string($userId)) {
                $userIds[] = $userId;
            }
        }

        $userIds = \array_values(\array_unique($userIds));
        if ($userIds === []) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', $userIds));
        $users = $this->userRepository->search($criteria, $context)->getEntities();

        $channelIdsByUserId = [];
        foreach ($users as $user) {
            $channelIdsByUserId[$user->getId()] = $user->getChannelId();
        }

        return $channelIdsByUserId;
    }

    private function isUserEntity(ImportExportBeforeImportRecordEvent $event): bool
    {
        return $event->getConfig()->get('sourceEntity') === UserDefinition::ENTITY_NAME;
    }

    private function isUpdateEvent(ImportExportBeforeImportRecordEvent $event): bool
    {
        return $event->getConfig()->get('updateEntities') ?? true;
    }

    /**
     * @param array<string, mixed> $record
     */
    private function hasValidUuid(array $record, string $key): bool
    {
        $value = $record[$key] ?? null;

        return \is_string($value) && Uuid::isValid($value);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function getChannelId(array $record): ?string
    {
        $channelId = $record['channelId'] ?? null;
        if (\is_string($channelId) && Uuid::isValid($channelId)) {
            return $channelId;
        }

        $channel = $record['channel'] ?? null;
        if (!\is_array($channel)) {
            return null;
        }

        $channelId = $channel['id'] ?? null;

        return \is_string($channelId) && Uuid::isValid($channelId) ? $channelId : null;
    }

    private function createUserNumberSearchCriteria(string $userNumber, ?string $excludedUserId): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userNumber', $userNumber));

        if ($excludedUserId !== null) {
            $criteria->addFilter(new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('id', $excludedUserId)]));
        }

        return $criteria;
    }
}

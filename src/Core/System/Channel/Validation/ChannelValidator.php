<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @phpstan-type CurrentLanguageStates list<array{channel_id: string, current_default: string, language_id: string}>
 */
class ChannelValidator implements EventSubscriberInterface
{
    private const INSERT_VALIDATION_MESSAGE = 'The channel with id "%s" does not have a default channel language id in the language list.';
    private const INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID';

    private const DUPLICATED_ENTRY_VALIDATION_MESSAGE = 'The channel language "%s" for the channel "%s" already exists.';
    private const DUPLICATED_ENTRY_VALIDATION_CODE = 'SYSTEM__DUPLICATED_CHANNEL_LANGUAGE';

    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of channel with id "%s"';
    private const UPDATE_VALIDATION_CODE = 'SYSTEM__CANNOT_UPDATE_DEFAULT_LANGUAGE_ID';

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the channel with id "%s".';
    private const DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID';

    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'handleChannelLanguageIds',
        ];
    }

    public function handleChannelLanguageIds(PreWriteValidationEvent $event): void
    {
        $mapping = $this->extractMapping($event);

        if ($mapping->count() === 0) {
            return;
        }

        $channelIds = $mapping->getKeys();
        $states = $this->fetchCurrentLanguageStates($channelIds);

        $this->mergeCurrentStatesWithMapping($mapping, $states);

        $this->validateLanguages($mapping, $event);
    }

    private function extractMapping(PreWriteValidationEvent $event): Mapping
    {
        $mapping = new Mapping();
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === ChannelDefinition::ENTITY_NAME) {
                $this->handleChannelMapping($mapping, $command);

                continue;
            }

            if ($command->getEntityName() === ChannelLanguageDefinition::ENTITY_NAME) {
                $this->handleChannelLanguageMapping($mapping, $command);
            }
        }

        return $mapping;
    }

    private function handleChannelMapping(Mapping $mapping, WriteCommand $command): void
    {
        if (!isset($command->getPayload()['language_id'])) {
            return;
        }

        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
        $channelData = $mapping->get($id);
        if ($channelData === null) {
            $channelData = new ChannelData();
            $mapping->set($id, $channelData);
        }

        if ($command instanceof UpdateCommand) {
            $channelData->updateId = Uuid::fromBytesToHex($command->getPayload()['language_id']);

            return;
        }

        if (!$command instanceof InsertCommand || !$this->isSupportedChannelType($command)) {
            return;
        }

        $channelData->newDefault = Uuid::fromBytesToHex($command->getPayload()['language_id']);
        $channelData->inserts = [];
    }

    private function isSupportedChannelType(WriteCommand $command): bool
    {
        $typeId = Uuid::fromBytesToHex($command->getPayload()['type_id']);

        return $typeId === Defaults::CHANNEL_TYPE_WEB
            || $typeId === Defaults::CHANNEL_TYPE_API;
    }

    private function handleChannelLanguageMapping(Mapping $mapping, WriteCommand $command): void
    {
        $language = Uuid::fromBytesToHex($command->getPrimaryKey()['language_id']);
        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['channel_id']);

        $channelData = $mapping->get($id);
        if ($channelData === null) {
            $channelData = new ChannelData();
            $mapping->set($id, $channelData);
        }

        if ($command instanceof DeleteCommand) {
            $channelData->deletions[] = $language;

            return;
        }

        if ($command instanceof InsertCommand) {
            $inserts = $channelData->inserts ?? [];
            $inserts[] = $language;
            $channelData->inserts = $inserts;
        }
    }

    private function validateLanguages(Mapping $mapping, PreWriteValidationEvent $event): void
    {
        $inserts = [];
        $duplicates = [];
        $deletions = [];
        $updates = [];

        foreach ($mapping as $channelId => $channelData) {
            if ($channelData->inserts !== null) {
                if ($this->isInvalidInsertCase($channelData)) {
                    $inserts[$channelId] = $channelData->newDefault;
                }

                $duplicatedIds = $this->getDuplicates($channelData);

                if ($duplicatedIds !== []) {
                    $duplicates[$channelId] = $duplicatedIds;
                }
            }

            $deletedDefault = $this->findDeletedDefaultLanguageId($channelData);
            if ($deletedDefault !== null) {
                $deletions[$channelId] = $deletedDefault;
            }

            if ($channelData->updateId !== null && $this->isInvalidUpdateCase($channelData)) {
                $updates[$channelId] = $channelData->updateId;
            }
        }

        $this->writeDuplicateViolationExceptions($duplicates, $event);
        $this->writeViolationExceptions($inserts, self::INSERT_VALIDATION_MESSAGE, self::INSERT_VALIDATION_CODE, $event);
        $this->writeViolationExceptions($deletions, self::DELETE_VALIDATION_MESSAGE, self::DELETE_VALIDATION_CODE, $event);
        $this->writeViolationExceptions($updates, self::UPDATE_VALIDATION_MESSAGE, self::UPDATE_VALIDATION_CODE, $event);
    }

    /**
     * @phpstan-assert-if-true !null $channelData->newDefault
     */
    private function isInvalidInsertCase(ChannelData $channelData): bool
    {
        if ($channelData->newDefault === null) {
            return false;
        }

        if ($channelData->inserts === null) {
            throw ChannelException::invalidMappingOperation('Inserts are not allowed to be null while calling this method.');
        }

        return !\in_array($channelData->newDefault, $channelData->inserts, true);
    }

    private function isInvalidUpdateCase(ChannelData $channelData): bool
    {
        $updateId = $channelData->updateId;

        return !\in_array($updateId, $channelData->state, true)
            && !($channelData->newDefault === null && $updateId === $channelData->currentDefault)
            && !($channelData->inserts !== null && \in_array($updateId, $channelData->inserts, true));
    }

    /**
     * Compares the deletions against the default language in effect after this write rather than the stored
     * one, so that assigning a new default and removing the previous one in a single write stays valid.
     */
    private function findDeletedDefaultLanguageId(ChannelData $channelData): ?string
    {
        $default = $channelData->updateId ?? $channelData->newDefault ?? $channelData->currentDefault;

        if ($default === null || !\in_array($default, $channelData->deletions, true)) {
            return null;
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    private function getDuplicates(ChannelData $channelData): array
    {
        if ($channelData->inserts === null) {
            throw ChannelException::invalidMappingOperation('Inserts are not allowed to be null while calling this method.');
        }

        return array_values(array_intersect($channelData->state, $channelData->inserts));
    }

    /**
     * @param array<string, list<string>> $duplicates
     */
    private function writeDuplicateViolationExceptions(array $duplicates, PreWriteValidationEvent $event): void
    {
        if (!$duplicates) {
            return;
        }

        $violations = new ConstraintViolationList();

        foreach ($duplicates as $id => $duplicateLanguages) {
            foreach ($duplicateLanguages as $languageId) {
                $violations->add(new ConstraintViolation(
                    \sprintf(self::DUPLICATED_ENTRY_VALIDATION_MESSAGE, $languageId, $id),
                    \sprintf(self::DUPLICATED_ENTRY_VALIDATION_MESSAGE, '{{ languageId }}', '{{ channelId }}'),
                    [
                        '{{ channelId }}' => $id,
                        '{{ languageId }}' => $languageId,
                    ],
                    null,
                    '/',
                    null,
                    null,
                    self::DUPLICATED_ENTRY_VALIDATION_CODE
                ));
            }
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param array<string, string> $invalidRecords
     */
    private function writeViolationExceptions(
        array $invalidRecords,
        string $messageTemplate,
        string $validationCode,
        PreWriteValidationEvent $event
    ): void {
        if (!$invalidRecords) {
            return;
        }

        $violations = new ConstraintViolationList();
        foreach (array_keys($invalidRecords) as $id) {
            $violations->add(new ConstraintViolation(
                \sprintf($messageTemplate, $id),
                \sprintf($messageTemplate, '{{ channelId }}'),
                ['{{ channelId }}' => $id],
                null,
                '/',
                null,
                null,
                $validationCode
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param list<string> $channelIds
     *
     * @return CurrentLanguageStates
     */
    private function fetchCurrentLanguageStates(array $channelIds): array
    {
        /** @var CurrentLanguageStates $result */
        $result = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(channel.id)) AS channel_id,
            LOWER(HEX(channel.language_id)) AS current_default,
            LOWER(HEX(mapping.language_id)) AS language_id
            FROM channel
            LEFT JOIN channel_language mapping
                ON mapping.channel_id = channel.id
                WHERE channel.id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($channelIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $result;
    }

    /**
     * @param CurrentLanguageStates $states
     */
    private function mergeCurrentStatesWithMapping(Mapping $mapping, array $states): void
    {
        if ($states === []) {
            return;
        }

        foreach ($states as $record) {
            $id = $record['channel_id'];
            if (!$mapping->has($id)) {
                continue;
            }

            $channelData = $mapping->get($id);

            $channelData->currentDefault = $record['current_default'];
            $channelData->state[] = $record['language_id'];
            $channelData->inserts = array_values(array_filter(
                $channelData->inserts ?? [],
                static fn (string $value): bool => $value !== $record['language_id']
            ));

            if ($channelData->inserts === []) {
                $channelData->inserts = null;
            }
        }
    }
}

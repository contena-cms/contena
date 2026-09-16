<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Validation;

use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @phpstan-type CurrentChannelStates list<array<string, string>>
 */
class ChannelValidator implements EventSubscriberInterface
{
    private const INSERT_VALIDATION_MESSAGE = 'The channel with id "%s" does not have a default channel language id in the language list.';
    private const INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID';

    private const UPDATE_VALIDATION_MESSAGE = 'Cannot update default language id because the given id is not in the language list of channel with id "%s"';
    private const UPDATE_VALIDATION_CODE = 'SYSTEM__CANNOT_UPDATE_DEFAULT_LANGUAGE_ID';

    private const DELETE_VALIDATION_MESSAGE = 'Cannot delete default language id from language list of the channel with id "%s".';
    private const DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID';

    private const CURRENCY_INSERT_VALIDATION_MESSAGE = 'The channel with id "%s" does not have a default channel currency id in the currency list.';
    private const CURRENCY_INSERT_VALIDATION_CODE = 'SYSTEM__NO_GIVEN_DEFAULT_CURRENCY_ID';

    private const CURRENCY_UPDATE_VALIDATION_MESSAGE = 'Cannot update default currency id because the given id is not in the currency list of channel with id "%s"';
    private const CURRENCY_UPDATE_VALIDATION_CODE = 'SYSTEM__CANNOT_UPDATE_DEFAULT_CURRENCY_ID';

    private const CURRENCY_DELETE_VALIDATION_MESSAGE = 'Cannot delete default currency id from currency list of the channel with id "%s".';
    private const CURRENCY_DELETE_VALIDATION_CODE = 'SYSTEM__CANNOT_DELETE_DEFAULT_CURRENCY_ID';

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
        $this->validateMapping(
            event: $event,
            defaultField: 'language_id',
            mappingEntity: ChannelLanguageDefinition::ENTITY_NAME,
            mappingTable: 'channel_language',
            mappingField: 'language_id',
            insertValidationMessage: self::INSERT_VALIDATION_MESSAGE,
            insertValidationCode: self::INSERT_VALIDATION_CODE,
            deleteValidationMessage: self::DELETE_VALIDATION_MESSAGE,
            deleteValidationCode: self::DELETE_VALIDATION_CODE,
            updateValidationMessage: self::UPDATE_VALIDATION_MESSAGE,
            updateValidationCode: self::UPDATE_VALIDATION_CODE,
        );

        $this->validateMapping(
            event: $event,
            defaultField: 'currency_id',
            mappingEntity: ChannelCurrencyDefinition::ENTITY_NAME,
            mappingTable: 'channel_currency',
            mappingField: 'currency_id',
            insertValidationMessage: self::CURRENCY_INSERT_VALIDATION_MESSAGE,
            insertValidationCode: self::CURRENCY_INSERT_VALIDATION_CODE,
            deleteValidationMessage: self::CURRENCY_DELETE_VALIDATION_MESSAGE,
            deleteValidationCode: self::CURRENCY_DELETE_VALIDATION_CODE,
            updateValidationMessage: self::CURRENCY_UPDATE_VALIDATION_MESSAGE,
            updateValidationCode: self::CURRENCY_UPDATE_VALIDATION_CODE,
        );
    }

    private function validateMapping(
        PreWriteValidationEvent $event,
        string $defaultField,
        string $mappingEntity,
        string $mappingTable,
        string $mappingField,
        string $insertValidationMessage,
        string $insertValidationCode,
        string $deleteValidationMessage,
        string $deleteValidationCode,
        string $updateValidationMessage,
        string $updateValidationCode,
    ): void {
        $mapping = $this->extractMapping($event, $defaultField, $mappingEntity, $mappingField);
        if ($mapping->count() === 0) {
            return;
        }

        $states = $this->fetchCurrentStates($mapping->getKeys(), $defaultField, $mappingTable, $mappingField);
        $this->mergeCurrentStatesWithMapping($mapping, $states, $mappingField);
        $this->validateMappingData(
            mapping: $mapping,
            event: $event,
            insertValidationMessage: $insertValidationMessage,
            insertValidationCode: $insertValidationCode,
            deleteValidationMessage: $deleteValidationMessage,
            deleteValidationCode: $deleteValidationCode,
            updateValidationMessage: $updateValidationMessage,
            updateValidationCode: $updateValidationCode,
        );
    }

    private function extractMapping(PreWriteValidationEvent $event, string $defaultField, string $mappingEntity, string $mappingField): Mapping
    {
        $mapping = new Mapping();
        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() === ChannelDefinition::ENTITY_NAME) {
                $this->handleChannelMapping($mapping, $command, $defaultField);

                continue;
            }

            if ($command->getEntityName() === $mappingEntity) {
                $this->handleChannelMappingCommand($mapping, $command, $mappingField);
            }
        }

        return $mapping;
    }

    private function handleChannelMapping(Mapping $mapping, WriteCommand $command, string $defaultField): void
    {
        if (!isset($command->getPayload()[$defaultField])) {
            return;
        }

        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['id']);
        $channelData = $mapping->get($id);
        if ($channelData === null) {
            $channelData = new ChannelData();
            $mapping->set($id, $channelData);
        }

        if ($command instanceof UpdateCommand) {
            $channelData->updateId = Uuid::fromBytesToHex($command->getPayload()[$defaultField]);

            return;
        }

        if (!$command instanceof InsertCommand) {
            return;
        }

        $channelData->newDefault = Uuid::fromBytesToHex($command->getPayload()[$defaultField]);
        $channelData->inserts = [];
    }

    private function handleChannelMappingCommand(Mapping $mapping, WriteCommand $command, string $mappingField): void
    {
        $mappingId = Uuid::fromBytesToHex($command->getPrimaryKey()[$mappingField]);
        $id = Uuid::fromBytesToHex($command->getPrimaryKey()['channel_id']);

        $channelData = $mapping->get($id);
        if ($channelData === null) {
            $channelData = new ChannelData();
            $mapping->set($id, $channelData);
        }

        if ($command instanceof DeleteCommand) {
            $channelData->deletions[] = $mappingId;

            return;
        }

        if ($command instanceof InsertCommand) {
            $inserts = $channelData->inserts ?? [];
            $inserts[] = $mappingId;
            $channelData->inserts = $inserts;
        }
    }

    private function validateMappingData(
        Mapping $mapping,
        PreWriteValidationEvent $event,
        string $insertValidationMessage,
        string $insertValidationCode,
        string $deleteValidationMessage,
        string $deleteValidationCode,
        string $updateValidationMessage,
        string $updateValidationCode,
    ): void {
        $inserts = [];
        $deletions = [];
        $updates = [];

        foreach ($mapping as $channelId => $channelData) {
            if ($channelData->inserts !== null && $this->isInvalidInsertCase($channelData)) {
                $inserts[$channelId] = $channelData->newDefault;
            }

            $deletedDefault = $this->findDeletedDefaultMappingId($channelData);
            if ($deletedDefault !== null) {
                $deletions[$channelId] = $deletedDefault;
            }

            if ($channelData->updateId !== null && $this->isInvalidUpdateCase($channelData)) {
                $updates[$channelId] = $channelData->updateId;
            }
        }

        $this->writeViolationExceptions($inserts, $insertValidationMessage, $insertValidationCode, $event);
        $this->writeViolationExceptions($deletions, $deleteValidationMessage, $deleteValidationCode, $event);
        $this->writeViolationExceptions($updates, $updateValidationMessage, $updateValidationCode, $event);
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

    private function findDeletedDefaultMappingId(ChannelData $channelData): ?string
    {
        $default = $channelData->updateId ?? $channelData->newDefault ?? $channelData->currentDefault;

        if ($default === null || !\in_array($default, $channelData->deletions, true)) {
            return null;
        }

        return $default;
    }

    /**
     * @param array<string, string> $invalidRecords
     */
    private function writeViolationExceptions(
        array $invalidRecords,
        string $messageTemplate,
        string $validationCode,
        PreWriteValidationEvent $event,
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
                $validationCode,
            ));
        }

        $event->getExceptions()->add(new WriteConstraintViolationException($violations));
    }

    /**
     * @param list<string> $channelIds
     *
     * @return CurrentChannelStates
     */
    private function fetchCurrentStates(array $channelIds, string $defaultField, string $mappingTable, string $mappingField): array
    {
        /** @var CurrentChannelStates $result */
        $result = $this->connection->fetchAllAssociative(
            \sprintf(
                'SELECT LOWER(HEX(channel.id)) AS channel_id,
                LOWER(HEX(channel.%s)) AS current_default,
                LOWER(HEX(mapping.%s)) AS %s
                FROM channel
                LEFT JOIN %s mapping
                    ON mapping.channel_id = channel.id
                    WHERE channel.id IN (:ids)',
                $defaultField,
                $mappingField,
                $mappingField,
                $mappingTable,
            ),
            ['ids' => Uuid::fromHexToBytesList($channelIds)],
            ['ids' => ArrayParameterType::BINARY],
        );

        return $result;
    }

    /**
     * @param CurrentChannelStates $states
     */
    private function mergeCurrentStatesWithMapping(Mapping $mapping, array $states, string $mappingField): void
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
            $channelData->state[] = $record[$mappingField];
            $channelData->inserts = array_values(array_filter(
                $channelData->inserts ?? [],
                static fn (string $value): bool => $value !== $record[$mappingField],
            ));

            if ($channelData->inserts === []) {
                $channelData->inserts = null;
            }
        }
    }
}

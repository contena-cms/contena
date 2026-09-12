<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Validation;

use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeMembershipAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeMembershipField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\AllowPlatformOwnedReference;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Contena\Tests\Integration\Core\System\User\TenantOwnedUserAggregateTest
 */
class DataScopeForeignKeyValidator implements EventSubscriberInterface
{
    final public const string VIOLATION_DATA_SCOPE_MISMATCH = 'FRAMEWORK__DATA_SCOPE_FOREIGN_KEY_MISMATCH';

    public function __construct(
        private readonly Connection $connection,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [PreWriteValidationEvent::class => 'preValidate'];
    }

    public function preValidate(PreWriteValidationEvent $event): void
    {
        $references = $this->collectReferences($event->getCommands());
        if ($references === []) {
            return;
        }

        $expectedDataScope = Uuid::fromHexToBytes($event->getContext()->getDataScopeId());
        $platformDataScope = Uuid::fromHexToBytes(Defaults::PLATFORM_DATA_SCOPE);
        $violations = new ConstraintViolationList();

        foreach ($references as $reference) {
            $owners = $this->loadOwners($reference['table'], $reference['field'], $reference['values'], $reference['binary']);

            foreach ($reference['commands'] as $commandReference) {
                $valueKey = $this->valueKey($commandReference['value']);
                if (!\array_key_exists($valueKey, $owners)) {
                    if (!$reference['membership']) {
                        continue;
                    }
                } elseif (\in_array($expectedDataScope, $owners[$valueKey], true)) {
                    continue;
                } elseif ($reference['allowPlatformOwned'] && \in_array($platformDataScope, $owners[$valueKey], true)) {
                    continue;
                }

                $message = 'The referenced entity is not visible in the current data scope.';
                $violations->add(new ConstraintViolation(
                    $message,
                    $message,
                    [],
                    null,
                    $commandReference['command']->getPath() . '/' . $commandReference['property'],
                    $reference['binary'] ? Uuid::fromBytesToHex($commandReference['value']) : $commandReference['value'],
                    null,
                    self::VIOLATION_DATA_SCOPE_MISMATCH,
                ));
            }
        }

        if (\count($violations) > 0) {
            $event->getExceptions()->add(new WriteConstraintViolationException($violations));
        }
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, array{table: string, field: string, binary: bool, membership: bool, allowPlatformOwned: bool, values: list<string>, commands: list<array{command: WriteCommand, property: string, value: string}>}>
     */
    private function collectReferences(array $commands): array
    {
        $references = [];

        foreach ($commands as $command) {
            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());
            if (!$definition->getFields()->filterInstance(DataScopeField::class)->first() instanceof DataScopeField) {
                continue;
            }

            foreach ($definition->getFields()->filterInstance(FkField::class) as $field) {
                if (!$field instanceof FkField
                    || $field instanceof DataScopeField
                    || $field instanceof DataScopeMembershipField
                    || $field instanceof CreatedByField
                    || $field instanceof UpdatedByField
                    || !$command->hasField($field->getStorageName())
                ) {
                    continue;
                }

                $value = $command->getPayload()[$field->getStorageName()];
                if (!\is_string($value) || $value === '') {
                    continue;
                }

                $referenceDefinition = $field->getReferenceDefinition();
                $membership = $referenceDefinition->getFields()->filterInstance(DataScopeMembershipAssociationField::class)->first();
                $dataScopeField = $referenceDefinition->getFields()->filterInstance(DataScopeField::class)->first();
                if (!$dataScopeField instanceof DataScopeField && !$membership instanceof DataScopeMembershipAssociationField) {
                    continue;
                }

                $referenceField = $referenceDefinition->getFields()->get($field->getReferenceField())
                    ?? $referenceDefinition->getFields()->getByStorageName($field->getReferenceField());
                if (!$referenceField instanceof StorageAware) {
                    continue;
                }

                $table = $referenceDefinition->getEntityName();
                $referenceStorageField = $referenceField->getStorageName();
                if ($membership instanceof DataScopeMembershipAssociationField) {
                    $table = $membership->getMappingDefinition()->getEntityName();
                    $referenceStorageField = $membership->getMappingLocalColumn();
                }

                $allowPlatformOwned = $field->is(AllowPlatformOwnedReference::class);
                $key = $table . '::' . $referenceStorageField . '::' . (int) $allowPlatformOwned;
                $references[$key] ??= [
                    'table' => $table,
                    'field' => $referenceStorageField,
                    'binary' => $referenceField instanceof IdField,
                    'membership' => $membership instanceof DataScopeMembershipAssociationField,
                    'allowPlatformOwned' => $allowPlatformOwned,
                    'values' => [],
                    'commands' => [],
                ];
                $references[$key]['values'][$this->valueKey($value)] = $value;
                $references[$key]['commands'][] = [
                    'command' => $command,
                    'property' => $field->getPropertyName(),
                    'value' => $value,
                ];
            }
        }

        foreach ($references as &$reference) {
            $reference['values'] = array_values($reference['values']);
        }
        unset($reference);

        return $references;
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, list<string>>
     */
    private function loadOwners(string $table, string $field, array $values, bool $binary): array
    {
        $owners = [];

        foreach (array_chunk($values, 500) as $chunk) {
            $rows = $this->connection->createQueryBuilder()
                ->select(EntityDefinitionQueryHelper::escape($field) . ' AS `reference_value`', '`data_scope_id`')
                ->from(EntityDefinitionQueryHelper::escape($table))
                ->where(EntityDefinitionQueryHelper::escape($field) . ' IN (:values)')
                ->setParameter('values', $chunk, $binary ? ArrayParameterType::BINARY : ArrayParameterType::STRING)
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                if (!\is_string($row['reference_value'])) {
                    continue;
                }

                if (\is_string($row['data_scope_id'])) {
                    $owners[$this->valueKey($row['reference_value'])][] = $row['data_scope_id'];
                }
            }
        }

        return $owners;
    }

    private function valueKey(string $value): string
    {
        return base64_encode($value);
    }
}

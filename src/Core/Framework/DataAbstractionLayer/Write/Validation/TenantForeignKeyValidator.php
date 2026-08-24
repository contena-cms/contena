<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Validation;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantMembershipAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantMembershipField;
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
class TenantForeignKeyValidator implements EventSubscriberInterface
{
    final public const string VIOLATION_TENANT_MISMATCH = 'FRAMEWORK__TENANT_FOREIGN_KEY_MISMATCH';

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

        $expectedTenantId = $event->getContext()->getTenantId();
        $expectedTenant = $expectedTenantId !== null ? Uuid::fromHexToBytes($expectedTenantId) : null;
        $violations = new ConstraintViolationList();

        foreach ($references as $reference) {
            $owners = $this->loadOwners($reference['table'], $reference['field'], $reference['values'], $reference['binary']);

            foreach ($reference['commands'] as $commandReference) {
                $valueKey = $this->valueKey($commandReference['value']);
                if (!\array_key_exists($valueKey, $owners)) {
                    if (!$reference['membership'] || $expectedTenant === null) {
                        continue;
                    }
                } elseif (\in_array($expectedTenant, $owners[$valueKey], true)) {
                    continue;
                }

                $message = 'The referenced tenant-scoped entity does not belong to the current tenant context.';
                $violations->add(new ConstraintViolation(
                    $message,
                    $message,
                    [],
                    null,
                    $commandReference['command']->getPath() . '/' . $commandReference['property'],
                    $reference['binary'] ? Uuid::fromBytesToHex($commandReference['value']) : $commandReference['value'],
                    null,
                    self::VIOLATION_TENANT_MISMATCH,
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
     * @return array<string, array{table: string, field: string, binary: bool, membership: bool, values: list<string>, commands: list<array{command: WriteCommand, property: string, value: string}>}>
     */
    private function collectReferences(array $commands): array
    {
        $references = [];

        foreach ($commands as $command) {
            $definition = $this->definitionRegistry->getByEntityName($command->getEntityName());
            if (!$definition->getFields()->filterInstance(TenantField::class)->first() instanceof TenantField) {
                continue;
            }

            foreach ($definition->getFields()->filterInstance(FkField::class) as $field) {
                if (!$field instanceof FkField
                    || $field instanceof TenantField
                    || $field instanceof TenantMembershipField
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
                $membership = $referenceDefinition->getFields()->filterInstance(TenantMembershipAssociationField::class)->first();
                $tenantField = $referenceDefinition->getFields()->filterInstance(TenantField::class)->first();
                if (!$tenantField instanceof TenantField && !$membership instanceof TenantMembershipAssociationField) {
                    continue;
                }

                $referenceField = $referenceDefinition->getFields()->get($field->getReferenceField())
                    ?? $referenceDefinition->getFields()->getByStorageName($field->getReferenceField());
                if (!$referenceField instanceof StorageAware) {
                    continue;
                }

                $table = $referenceDefinition->getEntityName();
                $referenceStorageField = $referenceField->getStorageName();
                if ($membership instanceof TenantMembershipAssociationField) {
                    $table = $membership->getMappingDefinition()->getEntityName();
                    $referenceStorageField = $membership->getMappingLocalColumn();
                }

                $key = $table . '::' . $referenceStorageField;
                $references[$key] ??= [
                    'table' => $table,
                    'field' => $referenceStorageField,
                    'binary' => $referenceField instanceof IdField,
                    'membership' => $membership instanceof TenantMembershipAssociationField,
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
     * @return array<string, list<string|null>>
     */
    private function loadOwners(string $table, string $field, array $values, bool $binary): array
    {
        $owners = [];

        foreach (array_chunk($values, 500) as $chunk) {
            $rows = $this->connection->createQueryBuilder()
                ->select(EntityDefinitionQueryHelper::escape($field) . ' AS `reference_value`', '`tenant_id`')
                ->from(EntityDefinitionQueryHelper::escape($table))
                ->where(EntityDefinitionQueryHelper::escape($field) . ' IN (:values)')
                ->setParameter('values', $chunk, $binary ? ArrayParameterType::BINARY : ArrayParameterType::STRING)
                ->executeQuery()
                ->fetchAllAssociative();

            foreach ($rows as $row) {
                if (!\is_string($row['reference_value'])) {
                    continue;
                }

                $owners[$this->valueKey($row['reference_value'])][] = \is_string($row['tenant_id']) ? $row['tenant_id'] : null;
            }
        }

        return $owners;
    }

    private function valueKey(string $value): string
    {
        return base64_encode($value);
    }
}

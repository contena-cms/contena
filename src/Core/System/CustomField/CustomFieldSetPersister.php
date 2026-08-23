<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Contena\Core\System\CustomField\Xml\CustomFields;

/**
 * @internal
 */
class CustomFieldSetPersister
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomFieldSetCollection> $customFieldSetRepository
     * @param EntityRepository<CustomFieldSetRelationCollection> $customFieldSetRelationRepository
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly Connection $connection,
        private readonly EntityRepository $customFieldSetRelationRepository,
        private readonly EntityRepository $customFieldRepository,
    ) {
    }

    /**
     * Sync plugin custom field sets from parsed XML definition.
     */
    public function sync(CustomFields $customFields, string $extensionName, Context $context): void
    {
        $context->scope(Context::SYSTEM_SCOPE, function (Context $innerContext) use ($customFields, $extensionName): void {
            $this->upsertCustomFieldSets($customFields, $extensionName, $innerContext);
        });
    }

    private function upsertCustomFieldSets(CustomFields $customFields, string $extensionName, Context $context): void
    {
        $existingCustomFieldSets = $this->getExistingCustomFieldSets($extensionName, $context);

        if ($customFields->getCustomFieldSets() === []) {
            if ($existingCustomFieldSets !== []) {
                $this->deleteObsoleteIds(
                    array_values($existingCustomFieldSets),
                    [],
                    [],
                    $context
                );
            }

            return;
        }

        $payload = [];
        $obsoleteRelations = [];
        $obsoleteFields = [];

        foreach ($customFields->getCustomFieldSets() as $customFieldSet) {
            if (!\array_key_exists($customFieldSet->getName(), $existingCustomFieldSets)) {
                $existingRelations = $existingFields = [];
                $entityData = $customFieldSet->toEntityArray($existingRelations, $existingFields);
                $entityData['extensionName'] = $extensionName;

                $payload[] = $entityData;

                continue;
            }

            $customFieldSetId = $existingCustomFieldSets[$customFieldSet->getName()];

            $existingRelations = Uuid::fromBytesToHexList(
                $this->connection->fetchAllKeyValue(
                    'SELECT entity_name, id FROM custom_field_set_relation WHERE set_id = :setId',
                    ['setId' => Uuid::fromHexToBytes($customFieldSetId)]
                )
            );
            $existingFields = Uuid::fromBytesToHexList(
                $this->connection->fetchAllKeyValue(
                    'SELECT name, id FROM custom_field WHERE set_id = :setId',
                    ['setId' => Uuid::fromHexToBytes($customFieldSetId)]
                )
            );
            $entityData = $customFieldSet->toEntityArray($existingRelations, $existingFields, $customFieldSetId);
            $entityData['extensionName'] = $extensionName;

            $obsoleteRelations = array_merge($obsoleteRelations, array_values($existingRelations));
            $obsoleteFields = array_merge($obsoleteFields, array_values($existingFields));

            $payload[] = $entityData;
            unset($existingCustomFieldSets[$customFieldSet->getName()]);
        }

        $this->deleteObsoleteIds(
            array_values($existingCustomFieldSets),
            $obsoleteRelations,
            $obsoleteFields,
            $context
        );

        $this->customFieldSetRepository->upsert($payload, $context);
    }

    /**
     * @return array<string, string> Map of set name => set id (hex)
     */
    private function getExistingCustomFieldSets(string $extensionName, Context $context): array
    {
        /** @var array<string, string> $allCustomFields */
        $allCustomFields = $this->connection->fetchAllKeyValue(
            'SELECT id, name FROM custom_field_set WHERE extension_name = :extensionName',
            ['extensionName' => $extensionName]
        );

        $groupedByName = [];
        foreach ($allCustomFields as $id => $name) {
            $groupedByName[$name][] = Uuid::fromBytesToHex($id);
        }

        $existingCustomFieldSets = [];
        foreach ($groupedByName as $name => $ids) {
            if (\count($ids) > 1) {
                // duplicate sets - delete all and let them be recreated
                $this->deleteObsoleteIds($ids, [], [], $context);
            } else {
                $existingCustomFieldSets[$name] = $ids[0];
            }
        }

        return $existingCustomFieldSets;
    }

    /**
     * @param list<string> $obsoleteFieldSets
     * @param list<string> $obsoleteRelations
     * @param list<string> $obsoleteFields
     */
    private function deleteObsoleteIds(array $obsoleteFieldSets, array $obsoleteRelations, array $obsoleteFields, Context $context): void
    {
        if ($obsoleteFieldSets !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteFieldSets);

            $this->customFieldSetRepository->delete($ids, $context);
        }

        if ($obsoleteRelations !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteRelations);

            $this->customFieldSetRelationRepository->delete($ids, $context);
        }

        if ($obsoleteFields !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], $obsoleteFields);

            $this->customFieldRepository->delete($ids, $context);
        }
    }
}

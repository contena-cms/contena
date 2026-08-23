<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(
    name: 'contena-entity-schema',
    title: 'Entity Schema',
    description: 'Get the field and association schema of a Contena entity definition: field names, types, and associations for building contena-entity-search criteria. Returns {success, data: {fields: [...], associations: [...]}}. See contena://entities resource for all available entity names.'
)]
#[McpToolGroup('entity')]
class EntitySchemaTool extends McpToolResponse
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
    ) {
    }

    public function __invoke(string $entity): string
    {
        if (!$this->registry->has($entity)) {
            return $this->error(\sprintf('Entity "%s" not found. Use the contena://entities resource for available entity names.', $entity));
        }

        $definition = $this->registry->getByEntityName($entity);

        $fields = [];
        $associations = [];

        foreach ($definition->getFields() as $field) {
            if ($field instanceof AssociationField) {
                $associations[] = [
                    'name' => $field->getPropertyName(),
                    'type' => match (true) {
                        $field instanceof ManyToManyAssociationField => 'many-to-many',
                        $field instanceof OneToManyAssociationField => 'one-to-many',
                        $field instanceof ManyToOneAssociationField => 'many-to-one',
                        $field instanceof OneToOneAssociationField => 'one-to-one',
                        default => 'association',
                    },
                    'entity' => $field->getReferenceDefinition()->getEntityName(),
                ];

                continue;
            }

            $fields[] = [
                'name' => $field->getPropertyName(),
                'type' => match (true) {
                    $field instanceof IdField => 'uuid',
                    $field instanceof FkField => 'fk',
                    $field instanceof BoolField => 'bool',
                    $field instanceof IntField => 'int',
                    $field instanceof FloatField => 'float',
                    $field instanceof DateTimeField => 'datetime',
                    $field instanceof JsonField => 'json',
                    default => 'string',
                },
                'required' => $field->is(Required::class),
            ];
        }

        return $this->success([
            'entity' => $entity,
            'fields' => $fields,
            'associations' => $associations,
        ]);
    }
}

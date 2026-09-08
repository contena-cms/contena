<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity\Schema;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Flag;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal The use of this class is reserved for the custom_entity feature
 *
 * @phpstan-import-type CustomEntityField from CustomEntitySchemaUpdater
 */
class DynamicEntityDefinition extends EntityDefinition
{
    /**
     * @var non-empty-string
     */
    protected string $name;

    /**
     * @var list<CustomEntityField>
     */
    protected array $fieldDefinitions;

    /**
     * @var list<Flag>
     */
    protected array $flags;

    protected ContainerInterface $container;

    /**
     * @param non-empty-string $name
     * @param list<CustomEntityField> $fields
     * @param list<Flag> $flags
     */
    public static function create(
        string $name,
        array $fields,
        array $flags,
        ContainerInterface $container
    ): DynamicEntityDefinition {
        $self = new self();
        $self->name = $name;
        $self->fieldDefinitions = $fields;
        $self->container = $container;
        $self->flags = $flags;

        return $self;
    }

    public function getEntityName(): string
    {
        return $this->name;
    }

    /**
     * @return list<Flag>
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    public function getDefaults(): array
    {
        $values = [];
        foreach ($this->fieldDefinitions as $fieldDefinition) {
            if (!isset($fieldDefinition['default'])) {
                continue;
            }

            $values[$fieldDefinition['name']] = $fieldDefinition['default'];
        }

        return $values;
    }

    protected function defineFields(): FieldCollection
    {
        $collection = DynamicFieldFactory::create($this->container, $this->getEntityName(), $this->fieldDefinitions);

        $collection->add(
            new IdField('id', 'id')->addFlags(new ApiAware(), new Required(), new PrimaryKey()),
        );

        return $collection;
    }
}

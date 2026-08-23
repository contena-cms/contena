<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class ToManyAssociationDependencyDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = '_test_to_many_association_dependency';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new Required(), new PrimaryKey()),

            new ManyToManyAssociationField('toManyDependency', ToManyAssociationDefinition::class, ToManyAssociationMappingDefinition::class, 'id', 'to_many_dependency_id'),
        ]);
    }
}

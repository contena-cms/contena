<?php
declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Fixture\SetNullOnDelete;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class SetNullOnDeleteManyToOneDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'set_null_on_delete_many_to_one';

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
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey()),
            new StringField('name', 'name')->addFlags(new ApiAware()),
            new OneToManyAssociationField('setNulls', SetNullOnDeleteParentDefinition::class, 'set_null_on_delete_many_to_one_id')->addFlags(new ApiAware(), new SetNullOnDelete()),
        ]);
    }
}

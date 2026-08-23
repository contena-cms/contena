<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Fixture\SetNullOnDelete;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class SetNullOnDeleteParentDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'set_null_on_delete_parent';

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
            new VersionField()->addFlags(new ApiAware()),
            new FkField('set_null_on_delete_many_to_one_id', 'setNullOnDeleteManyToOneId', SetNullOnDeleteManyToOneDefinition::class)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('manyToOne', 'set_null_on_delete_many_to_one_id', SetNullOnDeleteManyToOneDefinition::class, 'id', false)->addFlags(new ApiAware()),
            new StringField('name', 'name')->addFlags(new ApiAware()),
            new OneToManyAssociationField('setNulls', SetNullOnDeleteChildDefinition::class, 'set_null_on_delete_parent_id')->addFlags(new ApiAware(), new SetNullOnDelete()),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Fixture\SetNullOnDelete;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class SetNullOnDeleteChildDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'set_null_on_delete_child';

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

            new FkField('set_null_on_delete_parent_id', 'setNullOnDeleteParentId', SetNullOnDeleteParentDefinition::class)->addFlags(new ApiAware(), new Required()),
            new ReferenceVersionField(SetNullOnDeleteParentDefinition::class)->addFlags(new ApiAware(), new Required()),
            new StringField('name', 'name')->addFlags(new ApiAware()),
            new ManyToOneAssociationField('setNullOnDeleteParent', 'set_null_on_delete_parent_id', SetNullOnDeleteParentDefinition::class, 'id', false)->addFlags(new ApiAware()),
        ]);
    }
}

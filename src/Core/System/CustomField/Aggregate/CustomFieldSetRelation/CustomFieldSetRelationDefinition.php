<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;

class CustomFieldSetRelationDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'custom_field_set_relation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomFieldSetRelationCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomFieldSetRelationEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a custom field set relation.'),

            new FkField('set_id', 'customFieldSetId', CustomFieldSetDefinition::class)->addFlags(new Required())->setDescription('Unique identity of a custom field set.'),
            new StringField('entity_name', 'entityName', 63)->addFlags(new Required())->setDescription('Name of the entity.'),
            new ManyToOneAssociationField('customFieldSet', 'set_id', CustomFieldSetDefinition::class, 'id', false),
        ]);
    }

    protected function getParentDefinitionClass(): ?string
    {
        return CustomFieldSetDefinition::class;
    }
}

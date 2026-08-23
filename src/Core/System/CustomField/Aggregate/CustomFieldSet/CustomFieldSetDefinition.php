<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Aggregate\CustomFieldSet;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationDefinition;
use Contena\Core\System\CustomField\CustomFieldDefinition;

class CustomFieldSetDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'custom_field_set';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomFieldSetCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomFieldSetEntity::class;
    }

    public function getDefaults(): array
    {
        return ['position' => 1];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a custom field set.'),
            new StringField('name', 'name')->addFlags(new Required(), new Immutable())->setDescription('Unique name of a custom field set.'),
            new JsonField('config', 'config', [], [])->setDescription('Specifies detailed information about the component.'),
            new BoolField('active', 'active')->setDescription('When boolean value is `true`, the custom field set is enabled for use.'),
            new BoolField('global', 'global')->setDescription('When set to `true`, the custom field set is available system-wide.'),
            new IntField('position', 'position')->setDescription('The order of the tabs of your defined custom field set to be displayed.'),
            new StringField('extension_name', 'extensionName')->setDescription('Name of the plugin that owns this custom field set.'),

            new OneToManyAssociationField('customFields', CustomFieldDefinition::class, 'set_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('relations', CustomFieldSetRelationDefinition::class, 'set_id')->addFlags(new CascadeDelete()),
        ]);
    }
}

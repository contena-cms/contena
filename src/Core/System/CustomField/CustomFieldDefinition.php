<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;

class CustomFieldDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'custom_field';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CustomFieldCollection::class;
    }

    public function getEntityClass(): string
    {
        return CustomFieldEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getDefaults(): array
    {
        return [
            'channelApiAware' => true,
            'includeInSearch' => false,
        ];
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of a custom field.'),
            new StringField('name', 'name')->addFlags(new Required(), new Immutable())->setDescription('Unique name of a custom field.'),
            new StringField('type', 'type')->addFlags(new Required(), new Immutable())->setDescription('Custom field type can be selection, media , etc'),
            new JsonField('config', 'config', [], [])->setDescription('Specifies detailed information about the component.'),
            new BoolField('active', 'active')->setDescription('When boolean value is `true`, the custom field is enabled for use.'),
            new FkField('set_id', 'customFieldSetId', CustomFieldSetDefinition::class)->setDescription('Unique identity of customFieldSet.'),
            new BoolField('channel_api_aware', 'channelApiAware'),
            new BoolField('include_in_search', 'includeInSearch'),
            new ManyToOneAssociationField('customFieldSet', 'set_id', CustomFieldSetDefinition::class, 'id', false),
        ]);
    }
}

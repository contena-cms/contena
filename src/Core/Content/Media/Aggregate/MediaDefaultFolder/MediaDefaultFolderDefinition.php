<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaDefaultFolderDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'media_default_folder';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaDefaultFolderCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaDefaultFolderEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of media default folder.'),
            new DataScopeField()->setDescription('Non-null identity of the owning data scope for media default folder.'),

            new StringField('entity', 'entity')->addFlags(new Required())->setDescription('Indicates in which particular entity.'),
            new OneToOneAssociationField('folder', 'id', 'default_folder_id', MediaFolderDefinition::class),
            new CustomFields()->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}

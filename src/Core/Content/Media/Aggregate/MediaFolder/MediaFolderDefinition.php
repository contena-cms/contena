<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaFolder;

use Contena\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaFolderDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'media_folder';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaFolderCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaFolderEntity::class;
    }

    public function getDefaults(): array
    {
        return ['useParentConfiguration' => true];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of media folder.'),
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new BoolField('use_parent_configuration', 'useParentConfiguration')->setDescription('When boolean value is `true`, the folder inherits the configuration settings of its parent folder.'),
            new FkField('media_folder_configuration_id', 'configurationId', MediaFolderConfigurationDefinition::class)->addFlags(new Required())->setDescription('Unique identity of configuration.'),
            new FkField('default_folder_id', 'defaultFolderId', MediaDefaultFolderDefinition::class)->setDescription('Unique identity of default folder.'),
            new ParentFkField(self::class),
            new ParentAssociationField(self::class, 'id')->setDescription('Unique identity of media folder.'),
            new ChildrenAssociationField(self::class),
            new ChildCountField(),
            new TreePathField('path', 'path')->setDescription('A relative URL to the media folder.'),
            new OneToManyAssociationField('media', MediaDefinition::class, 'media_folder_id')->addFlags(new SetNullOnDelete()),
            new OneToOneAssociationField('defaultFolder', 'default_folder_id', 'id', MediaDefaultFolderDefinition::class, false),
            new ManyToOneAssociationField('configuration', 'media_folder_configuration_id', MediaFolderConfigurationDefinition::class, 'id', false),
            new StringField('name', 'name')->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING), new Required())->setDescription('Name of media folder.'),
            new CustomFields()->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Contena\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Contena\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Contena\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\User\UserDefinition;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MediaDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return MediaHydrator::class;
    }

    public function getRestrictDeleteMetaFields(): FieldCollection
    {
        return $this->getFields()->filter(
            static fn (Field $field) => \in_array($field->getPropertyName(), ['id', 'fileName', 'fileExtension'], true)
        );
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the media.'),
            (new FkField('user_id', 'userId', UserDefinition::class))->setDescription('Unique identity of the user'),
            (new FkField('media_folder_id', 'mediaFolderId', MediaFolderDefinition::class))->setDescription('Unique identity of the media folder.'),
            (new StringField('file_extension', 'fileExtension'))->addFlags(new ApiAware())->setDescription('Type of file indication. For example: jpeg, png.'),
            (new JsonField('media_type', 'mediaType'))->addFlags(new WriteProtected(), new Runtime())->setDescription('Type or format of media content, such as images, videos, or audio files.'),
            (new JsonField('config', 'config'))->addFlags(new ApiAware()),
            (new StringField('url', 'url'))->addFlags(new ApiAware(), new Runtime(['path', 'private', 'updatedAt'])),
            (new StringField('path', 'path', 2048))->addFlags(new ApiAware()),
            (new BoolField('has_file', 'hasFile'))->addFlags(new ApiAware(), new Runtime()),
            (new BoolField('private', 'private'))->addFlags(new ApiAware())->setDescription('When `true`, the media display is kept private.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BlobField('thumbnails_ro', 'thumbnailsRo'))->removeFlag(ApiAware::class)->addFlags(new Computed()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'media_id'))->addFlags(new ApiAware(), new Required()),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Generated thumbnail images in various sizes'),
            // reverse side of the associations, not available in store-api
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('avatarUsers', UserDefinition::class, 'avatar_id'))->addFlags(new SetNullOnDelete()),
            new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, 'id', false),
            (new OneToManyAssociationField('propertyGroupOptions', PropertyGroupOptionDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('mailTemplateMedia', MailTemplateMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productConfiguratorSettings', ProductConfiguratorSettingDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productOpenGraphImages', ProductDefinition::class, 'open_graph_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('productGuaranteeTerms', ProductDefinition::class, 'guarantee_terms_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('appPaymentMethods', AppPaymentMethodDefinition::class, 'original_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('appShippingMethods', AppShippingMethodDefinition::class, 'original_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new StringField('file_hash', 'fileHash'))->addFlags(new Computed()),
        ]);
    }
}

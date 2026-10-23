<?php declare(strict_types=1);

namespace Contena\Core\Content\Media;

use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\MailTemplate\Aggregate\MailTemplateMedia\MailTemplateMediaDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Contena\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Computed;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\AdminUser\AdminUserDefinition;

/**
 * @codeCoverageIgnore
 */
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
            (new FkField('admin_user_id', 'admin_userId', AdminUserDefinition::class))->setDescription('Unique identity of the admin_user'),
            (new FkField('media_folder_id', 'mediaFolderId', MediaFolderDefinition::class))->setDescription('Unique identity of the media folder.'),
            (new StringField('mime_type', 'mimeType'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::LOW_SEARCH_RANKING))->setDescription('A string sent along with a file indicating the type of the file. For example: image/jpeg.'),
            (new StringField('file_extension', 'fileExtension'))->addFlags(new ApiAware())->setDescription('Type of file indication. For example: jpeg, png.'),
            (new DateTimeField('uploaded_at', 'uploadedAt'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Date and time at which media was added.'),
            (new LongTextField('file_name', 'fileName'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING))->setDescription('Name of the media file uploaded.'),
            (new IntField('file_size', 'fileSize'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Size of the file media file uploaded.'),
            (new BlobField('media_type', 'mediaTypeRaw'))->removeFlag(ApiAware::class)->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
            (new JsonField('meta_data', 'metaData'))->addFlags(new ApiAware(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Details of the media file uploaded.'),
            (new JsonField('media_type', 'mediaType'))->addFlags(new WriteProtected(), new Runtime())->setDescription('Type or format of media content, such as images, videos, or audio files.'),
            (new JsonField('config', 'config'))->addFlags(new ApiAware()),
            (new TranslatedField('alt'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('title'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('url', 'url'))->addFlags(new ApiAware(), new Runtime(['path', 'private', 'updatedAt'])),
            (new StringField('path', 'path', 2048))->addFlags(new ApiAware()),
            (new BoolField('has_file', 'hasFile'))->addFlags(new ApiAware(), new Runtime()),
            (new BoolField('private', 'private'))->addFlags(new ApiAware())->setDescription('When `true`, the media display is kept private.'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new BlobField('thumbnails_ro', 'thumbnailsRo'))->removeFlag(ApiAware::class)->addFlags(new Computed()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'media_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, MediaTagDefinition::class, 'media_id', 'tag_id'))->addFlags(new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('thumbnails', MediaThumbnailDefinition::class, 'media_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Generated thumbnail images in various sizes'),
            // reverse side of the associations, not available in store-api
            new ManyToOneAssociationField('admin_user', 'admin_user_id', AdminUserDefinition::class, 'id', false),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('blogMedia', BlogMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('avatarUsers', AdminUserDefinition::class, 'avatar_id'))->addFlags(new SetNullOnDelete()),
            new ManyToOneAssociationField('mediaFolder', 'media_folder_id', MediaFolderDefinition::class, 'id', false),
            (new OneToManyAssociationField('propertyGroupOptions', PropertyGroupOptionDefinition::class, 'media_id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('mailTemplateMedia', MailTemplateMediaDefinition::class, 'media_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('blogOpenGraphImages', BlogDefinition::class, 'open_graph_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('blogGuaranteeTerms', BlogDefinition::class, 'guarantee_terms_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('cmsBlocks', CmsBlockDefinition::class, 'background_media_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('cmsSections', CmsSectionDefinition::class, 'background_media_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('cmsPages', CmsPageDefinition::class, 'preview_media_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('appPaymentMethods', AppPaymentMethodDefinition::class, 'original_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new OneToManyAssociationField('appShippingMethods', AppShippingMethodDefinition::class, 'original_media_id', 'id'))->addFlags(new SetNullOnDelete()),
            (new StringField('file_hash', 'fileHash'))->addFlags(new Computed()),
        ]);
    }
}

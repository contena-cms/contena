<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Aggregate\MediaThumbnail;

use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class MediaThumbnailDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'media_thumbnail';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MediaThumbnailCollection::class;
    }

    public function getEntityClass(): string
    {
        return MediaThumbnailEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return MediaDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of media thumbnail.'),
            new TenantField()->setDescription('Unique identity of the owning tenant.'),

            new FkField('media_id', 'mediaId', MediaDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of media.'),
            new FkField('media_thumbnail_size_id', 'mediaThumbnailSizeId', MediaThumbnailSizeDefinition::class)->addFlags(new ApiAware(), new Required()),

            new IntField('width', 'width')->addFlags(new ApiAware(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Width of the thumbnail.'),
            new IntField('height', 'height')->addFlags(new ApiAware(), new Required(), new WriteProtected(Context::SYSTEM_SCOPE))->setDescription('Height of the thumbnail.'),
            new StringField('url', 'url')->addFlags(new ApiAware(), new Runtime(['path', 'updatedAt']))->setDescription('Public url of media thumbnail.'),
            new StringField('path', 'path', 2048)->addFlags(new ApiAware()),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false),
            new ManyToOneAssociationField('mediaThumbnailSize', 'media_thumbnail_size_id', MediaThumbnailSizeDefinition::class, 'id', false),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}

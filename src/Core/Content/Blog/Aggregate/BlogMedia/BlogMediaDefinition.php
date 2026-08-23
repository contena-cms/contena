<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogMedia;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SetNullOnDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

class BlogMediaDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog_media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogMediaCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogMediaEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogMediaHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the Blog Media.'),
            new VersionField()->addFlags(new ApiAware()),

            new FkField('blog_id', 'blogId', BlogDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the blog.'),
            new ReferenceVersionField(BlogDefinition::class)->addFlags(new ApiAware(), new Required()),

            new FkField('media_id', 'mediaId', MediaDefinition::class)->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the media.'),
            new IntField('position', 'position')->addFlags(new ApiAware())->setDescription('The order of the images to be displayed for a blog.'),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id'),
            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id')->addFlags(new ApiAware()),
            new OneToManyAssociationField('coverBlogs', BlogDefinition::class, 'blog_media_id')->addFlags(new SetNullOnDelete(false)),
            new CustomFields()->addFlags(new ApiAware())->setDescription('Additional fields that offer a possibility to add own fields for the different program-areas.'),
        ]);
    }
}

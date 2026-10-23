<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\MainCategory;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @codeCoverageIgnore
 */
class MainCategoryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'main_category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return MainCategoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return MainCategoryEntity::class;
    }

    public function isInheritanceAware(): bool
    {
        return false;
    }

    public function isVersionAware(): bool
    {
        return false;
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of main category.'),

            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the blog.'),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the category.'),
            (new ReferenceVersionField(CategoryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('channel_id', 'channelId', ChannelDefinition::class))->addFlags(new ApiAware(), new Required())->setDescription('Unique identity of the sales channel.'),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class),
            new ManyToOneAssociationField('channel', 'channel_id', ChannelDefinition::class),
        ]);
    }
}

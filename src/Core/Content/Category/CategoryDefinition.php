<?php declare(strict_types=1);

namespace Contena\Core\Content\Category;

use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategoryTree\BlogCategoryTreeDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Cms\CmsPageDefinition;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\CustomEntity\CustomEntityDefinition;
use Contena\Core\System\Tag\TagDefinition;

class CategoryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'category';

    final public const TYPE_PAGE = 'page';

    final public const TYPE_LINK = 'link';

    final public const TYPE_FOLDER = 'folder';

    final public const LINK_TYPE_EXTERNAL = 'external';

    final public const LINK_TYPE_CATEGORY = 'category';

    final public const LINK_TYPE_BLOG = 'blog';

    final public const LINK_TYPE_LANDING_PAGE = 'landing_page';

    final public const BLOG_ASSIGNMENT_TYPE_BLOG = 'blog';

    final public const BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM = 'content_stream';

    final public const CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY = 'core.cms.default_category_cms_page';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return CategoryCollection::class;
    }

    public function getEntityClass(): string
    {
        return CategoryEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'displayNestedBlogs' => true,
            'type' => self::TYPE_PAGE,
            'blogAssignmentType' => self::BLOG_ASSIGNMENT_TYPE_BLOG,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return CategoryHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of category.'),
            (new VersionField())->addFlags(new ApiAware()),

            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('after_category_id', 'afterCategoryId', self::class))->addFlags(new ApiAware())->setDescription('Unique identity of the category under which the new category is to be created.'),
            (new ReferenceVersionField(self::class, 'after_category_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('media_id', 'mediaId', MediaDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of media added to identify category.'),

            (new BoolField('display_nested_products', 'displayNestedBlogs'))->addFlags(new ApiAware(), new Required())->setDescription('Shows nested categories on a blog category page.'),
            new AutoIncrementField(),

            (new TranslatedField('breadcrumb'))->addFlags(new ApiAware(), new WriteProtected()),
            (new TreeLevelField('level', 'level'))->addFlags(new ApiAware())->setDescription('An integer value that denotes the level of nesting of a particular category located in an hierarchical category tree.'),
            (new TreePathField('path', 'path'))->addFlags(new ApiAware())->setDescription('A relative URL to the category.'),
            (new ChildCountField())->addFlags(new ApiAware()),

            (new StringField('blog_assignment_type', 'blogAssignmentType'))->addFlags(new ApiAware(), new Required())->setDescription('Type of blog assignment: Dynamic blog group as or `content_stream` or Manual assignment as `blog`.'),
            (new BoolField('visible', 'visible'))->addFlags(new ApiAware())->setDescription('Displays categories on category page when true.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the category is listed for selection.'),

            (new IntField('visible_child_count', 'visibleChildCount'))->addFlags(new Runtime(), new ApiAware()),

            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new TranslatedField('slotConfig'),
            (new TranslatedField('linkType'))->addFlags(new ApiAware()),
            (new TranslatedField('internalLink'))->addFlags(new ApiAware()),
            (new TranslatedField('externalLink'))->addFlags(new ApiAware()),
            (new TranslatedField('linkNewTab'))->addFlags(new ApiAware()),
            (new TranslatedField('description'))->addFlags(new ApiAware()),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware()),
            (new TranslatedField('metaDescription'))->addFlags(new ApiAware()),
            (new TranslatedField('keywords'))->addFlags(new ApiAware()),

            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware())->setDescription('Unique identity of category.'),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware())->setDescription('Child categories within this category for hierarchical navigation'),

            (new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('Category image or banner'),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'category_id'))->addFlags(new ApiAware(), new Required()),
            (new ManyToManyAssociationField('blogs', BlogDefinition::class, BlogCategoryDefinition::class, 'category_id', 'blog_id'))->addFlags(new CascadeDelete(), new ReverseInherited('categories')),
            (new ManyToManyAssociationField('nestedBlogs', BlogDefinition::class, BlogCategoryTreeDefinition::class, 'category_id', 'blog_id'))->addFlags(new CascadeDelete(), new WriteProtected()),
            (new ManyToManyAssociationField('tags', TagDefinition::class, CategoryTagDefinition::class, 'category_id', 'tag_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Tags for organizing and filtering categories'),

            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new ApiAware())->setDescription('Unique identity of CMS page.'),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Required(), new ApiAware()),
            (new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware())->setDescription('CMS page layout for the category'),
            (new FkField('content_stream_id', 'contentStreamId', ContentStreamDefinition::class))->setDescription('Unique identity of blog stream.'),
            new ManyToOneAssociationField('contentStream', 'content_stream_id', ContentStreamDefinition::class, 'id', false),

            // custom entity specific fields
            (new FkField('custom_entity_type_id', 'customEntityTypeId', CustomEntityDefinition::class, 'id'))->addFlags(new ApiAware()),

            // Reverse Associations not available in store-api->setDescription('Unique identity of custom entity type.')
            new OneToManyAssociationField('navigationChannels', ChannelDefinition::class, 'navigation_category_id'),
            new OneToManyAssociationField('footerChannels', ChannelDefinition::class, 'footer_category_id'),
            new OneToManyAssociationField('serviceChannels', ChannelDefinition::class, 'service_category_id'),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'category_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key'))->addFlags(new ApiAware())->setDescription('SEO-friendly URLs for the category across different sales channels'),
        ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $fields->add(
                (new BoolField('cms_page_id_switched', 'cmsPageIdSwitched'))->addFlags(new Runtime(), new ApiAware(), new Deprecated('v6.7.9.0', 'v6.8.0.0')),
            );
        }

        return $fields;
    }
}

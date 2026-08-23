<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategoryTree\BlogCategoryTreeDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\NoConstraint;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Tag\TagDefinition;

class BlogDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'blog';

    final public const string TYPE_POST = 'post';

    final public const string TYPE_MEDIA = 'media';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return BlogCollection::class;
    }

    public function getEntityClass(): string
    {
        return BlogEntity::class;
    }

    /**
     * @return array{active: true, type: 'post'}
     */
    public function getDefaults(): array
    {
        return [
            'active' => true,
            'type' => self::TYPE_POST,
        ];
    }

    public function since(): ?string
    {
        return '6.8.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the blog.'),
            new VersionField()->addFlags(new ApiAware()),
            new FkField('blog_media_id', 'coverId', BlogMediaDefinition::class)->addFlags(new ApiAware(), new NoConstraint())->setDescription('Unique identity of a BlogMedia item used as blog cover.'),
            new ReferenceVersionField(BlogMediaDefinition::class)->addFlags(new ApiAware(), new Required()),
            new FkField('open_graph_media_id', 'openGraphMediaId', MediaDefinition::class)->addFlags(new ApiAware())->setDescription('Media used as Open Graph image for social media sharing.'),
            new AutoIncrementField(),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When true, the blog is available in assigned channels.'),
            new StringField('type', 'type')->addFlags(new ApiAware(), new Immutable(), new Required(), new Choice([
                self::TYPE_POST,
                self::TYPE_MEDIA,
            ]))->setDescription('The type of the blog, e.g., post or media.'),
            new DateTimeField('release_date', 'releaseDate')->addFlags(new ApiAware())->setDescription('Publication date of the blog.'),
            new ListField('category_tree', 'categoryTree', IdField::class)->addFlags(new ApiAware(), new WriteProtected())->setDescription('Internal field.'),
            new ManyToManyIdField('tag_ids', 'tagIds', 'tags')->addFlags(new ApiAware())->setDescription('Unique identities of tags.'),
            new ManyToManyIdField('category_ids', 'categoryIds', 'categories')->addFlags(new ApiAware())->setDescription('Unique identities of categories.'),
            new TranslatedField('metaDescription')->addFlags(new ApiAware()),
            new TranslatedField('name', true)->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('keywords')->addFlags(new ApiAware()),
            new TranslatedField('customSearchKeywords')->addFlags(new ApiAware()),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new TranslatedField('descriptionTeaser')->addFlags(new ApiAware())->setDescription('Read-only, HTML-stripped excerpt of the description, derived on write.'),
            new TranslatedField('metaTitle')->addFlags(new ApiAware()),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new TranslatedField('ogTitle')->addFlags(new ApiAware()),
            new TranslatedField('ogDescription')->addFlags(new ApiAware()),
            new ManyToOneAssociationField('cover', 'blog_media_id', BlogMediaDefinition::class, 'id')->addFlags(new ApiAware())->setDescription('Main image displayed in blog listings and detail pages.'),
            new ManyToOneAssociationField('openGraphMedia', 'open_graph_media_id', MediaDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Open Graph image for social media sharing.'),
            new OneToManyAssociationField('media', BlogMediaDefinition::class, 'blog_id')->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Blog media gallery.'),
            new OneToManyAssociationField('searchKeywords', BlogSearchKeywordDefinition::class, 'blog_id', 'id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('visibilities', BlogVisibilityDefinition::class, 'blog_id')->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('mainCategories', BlogMainCategoryDefinition::class, 'blog_id')->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Primary category assignments per channel for SEO and navigation.'),
            new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key')->addFlags(new ApiAware())->setDescription('SEO-friendly URLs for the blog across different channels.'),
            new ManyToManyAssociationField('categories', CategoryDefinition::class, BlogCategoryDefinition::class, 'blog_id', 'category_id')->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Categories this blog is assigned to.'),
            new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, BlogCategoryTreeDefinition::class, 'blog_id', 'category_id')->addFlags(new ApiAware(), new CascadeDelete(false), new WriteProtected())->setDescription('Read-only category tree including all parent categories.'),
            new ManyToManyAssociationField('tags', TagDefinition::class, BlogTagDefinition::class, 'blog_id', 'tag_id')->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Tags for organizing and filtering blogs.'),
            new TranslationsAssociationField(BlogTranslationDefinition::class, 'blog_id')->addFlags(new ApiAware(), new Required()),
        ]);
    }
}

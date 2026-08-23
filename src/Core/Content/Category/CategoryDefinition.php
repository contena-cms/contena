<?php declare(strict_types=1);

namespace Contena\Core\Content\Category;

use Contena\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
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
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Tag\TagDefinition;

class CategoryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'category';

    final public const string TYPE_PAGE = 'page';

    final public const string TYPE_LINK = 'link';

    final public const string TYPE_FOLDER = 'folder';

    final public const string LINK_TYPE_EXTERNAL = 'external';

    final public const string LINK_TYPE_CATEGORY = 'category';

    final public const string LINK_TYPE_BLOG = 'blog';

    final public const string LINK_TYPE_LANDING_PAGE = 'landing_page';

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
            'type' => self::TYPE_PAGE,
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
            new TenantField()->setDescription('Unique identity of the owning tenant.'),
            new IdField('id', 'id')->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of category.'),
            new VersionField()->addFlags(new ApiAware()),

            new ParentFkField(self::class)->addFlags(new ApiAware()),
            new ReferenceVersionField(self::class, 'parent_version_id')->addFlags(new ApiAware(), new Required()),

            new FkField('after_category_id', 'afterCategoryId', self::class)->addFlags(new ApiAware())->setDescription('Unique identity of the category under which the new category is to be created.'),
            new ReferenceVersionField(self::class, 'after_category_version_id')->addFlags(new ApiAware(), new Required()),

            new FkField('media_id', 'mediaId', MediaDefinition::class)->addFlags(new ApiAware())->setDescription('Unique identity of media added to identify category.'),

            new AutoIncrementField(),

            new TranslatedField('breadcrumb')->addFlags(new ApiAware(), new WriteProtected()),
            new TreeLevelField('level', 'level')->addFlags(new ApiAware())->setDescription('An integer value that denotes the level of nesting of a particular category located in an hierarchical category tree.'),
            new TreePathField('path', 'path')->addFlags(new ApiAware())->setDescription('A relative URL to the category.'),
            new ChildCountField()->addFlags(new ApiAware()),

            new StringField('type', 'type')->addFlags(new ApiAware(), new Required(), new Choice([
                self::TYPE_PAGE,
                self::TYPE_LINK,
                self::TYPE_FOLDER,
            ]))->setDescription('Type of categories like `page`, `folder`, `link`.'),
            new BoolField('visible', 'visible')->addFlags(new ApiAware())->setDescription('Displays categories on category page when true.'),
            new BoolField('active', 'active')->addFlags(new ApiAware())->setDescription('When boolean value is `true`, the category is listed for selection.'),

            new IntField('visible_child_count', 'visibleChildCount')->addFlags(new Runtime(), new ApiAware()),

            new TranslatedField('name')->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new TranslatedField('customFields')->addFlags(new ApiAware()),
            new TranslatedField('linkType')->addFlags(new ApiAware()),
            new TranslatedField('internalLink')->addFlags(new ApiAware()),
            new TranslatedField('externalLink')->addFlags(new ApiAware()),
            new TranslatedField('linkNewTab')->addFlags(new ApiAware()),
            new TranslatedField('description')->addFlags(new ApiAware()),
            new TranslatedField('metaTitle')->addFlags(new ApiAware()),
            new TranslatedField('metaDescription')->addFlags(new ApiAware()),
            new TranslatedField('keywords')->addFlags(new ApiAware()),

            new ParentAssociationField(self::class, 'id')->addFlags(new ApiAware())->setDescription('Unique identity of category.'),
            new ChildrenAssociationField(self::class)->addFlags(new ApiAware())->setDescription('Child categories within this category for hierarchical navigation'),

            new ManyToOneAssociationField('media', 'media_id', MediaDefinition::class, 'id', false)->addFlags(new ApiAware())->setDescription('Category image or banner'),
            new TranslationsAssociationField(CategoryTranslationDefinition::class, 'category_id')->addFlags(new ApiAware(), new Required()),
            new ManyToManyAssociationField('tags', TagDefinition::class, CategoryTagDefinition::class, 'category_id', 'tag_id')->addFlags(new ApiAware())->setDescription('Tags for organizing and filtering categories'),

            // Reverse associations are not available in the Channel API.
            new OneToManyAssociationField('navigationChannels', ChannelDefinition::class, 'navigation_category_id'),
            new OneToManyAssociationField('footerChannels', ChannelDefinition::class, 'footer_category_id'),
            new OneToManyAssociationField('serviceChannels', ChannelDefinition::class, 'service_category_id'),
            new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key')->addFlags(new ApiAware())->setDescription('SEO-friendly URLs for the category across different channels'),
        ]);

        return $fields;
    }
}

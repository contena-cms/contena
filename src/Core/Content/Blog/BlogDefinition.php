<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCategoryTree\BlogCategoryTreeDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCustomFieldSet\BlogCustomFieldSetDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Aggregate\ContentStreamMapping\ContentStreamMappingDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
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
use Contena\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiCriteriaAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Choice;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Immutable;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\NoConstraint;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ListField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Feature;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Contena\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\System\Unit\UnitDefinition;
use Contena\Core\System\User\Aggregate\UserWishlistBlog\UserWishlistBlogDefinition;

class BlogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog';

    final public const CONFIG_KEY_DEFAULT_CMS_PAGE_BLOG = 'core.cms.default_product_cms_page';

    final public const TYPE_PHYSICAL = 'physical';

    final public const TYPE_DIGITAL = 'digital';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isInheritanceAware(): bool
    {
        return true;
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
     * @return array{isCloseout: false, minPurchase: 1, purchaseSteps: 1, shippingFree: false, restockTime: null, active: true, markAsTopseller: false, type: 'physical'}
     */
    public function getDefaults(): array
    {
        return [
            'isCloseout' => false,
            'minPurchase' => 1,
            'purchaseSteps' => 1,
            'shippingFree' => false,
            'restockTime' => null,
            'active' => true,
            'markAsTopseller' => false,
            'type' => self::TYPE_PHYSICAL,
        ];
    }

    /**
     * @return array{type: 'physical'}
     */
    public function getChildDefaults(): array
    {
        return [
            'type' => self::TYPE_PHYSICAL,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogHydrator::class;
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of the blog.'),
            (new VersionField())->addFlags(new ApiAware()),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new ApiAware(), new Required()),

            (new FkField('unit_id', 'unitId', UnitDefinition::class))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of the unit.'),
            (new FkField('tax_id', 'taxId', TaxDefinition::class))->addFlags(new ApiAware(), new Inherited(), new Required())->setDescription('Unique identity of tax.'),
            (new FkField('blog_media_id', 'coverId', BlogMediaDefinition::class))->addFlags(new ApiAware(), new Inherited(), new NoConstraint())->setDescription('Unique identity of a BlogMedia item used as blog cover.'),
            (new ReferenceVersionField(BlogMediaDefinition::class))->addFlags(new ApiAware(), new Inherited(), new Required()),
            (new FkField('canonical_product_id', 'canonicalBlogId', self::class))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of canonical blog.'),
            (new ReferenceVersionField(self::class, 'canonical_product_version_id'))->addFlags(new ApiAware(), new Inherited(), new Required()),
            (new FkField('cms_page_id', 'cmsPageId', CmsPageDefinition::class))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of CMS page.'),
            (new ReferenceVersionField(CmsPageDefinition::class))->addFlags(new Inherited(), new Required(), new ApiAware()),
            (new FkField('open_graph_media_id', 'openGraphMediaId', MediaDefinition::class))->addFlags(new ApiAware(), new Inherited())->setDescription('Media used as Open Graph image for social media sharing.'),
            (new FkField('guarantee_terms_media_id', 'guaranteeTermsMediaId', MediaDefinition::class))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of the document with the producer commercial durability guarantee terms.'),

            (new PriceField('price', 'price'))->addFlags(new Inherited(), new Required(), new ApiCriteriaAware())->setDescription('Price of the blog.'),
            (new NumberRangeField('blog_number', 'blogNumber'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING, false), new Required())->setDescription('Unique number assigned to individual blogs. Define rules for automatic assignment of every blog creation as per your number range.'),
            // @deprecated tag:v6.8.0 - minValue: 0 will be applied unconditionally
            (new IntField('restock_time', 'restockTime', minValue: Feature::isActive('v6.8.0.0') ? 0 : null))->addFlags(new ApiAware(), new Inherited())->setDescription('The restock time in days indicates how long it will take until a sold out item is back in stock.'),
            new AutoIncrementField(),
            (new BoolField('active', 'active'))->addFlags(new ApiAware(), new Inherited())->setDescription('When boolean value is `true`, the blogs are available for selection in the frontend for purchase.'),
            (new BoolField('available', 'available'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Indicates weather the blog is available or not.'),
            (new BoolField('is_closeout', 'isCloseout'))->addFlags(new ApiAware(), new Inherited())->setDescription('When the value is set to true, the blog is hidden when sold out.'),
            (new IntField('available_stock', 'availableStock'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Indicates the number of blogs still available. This value results from the stock minus the open orders.'),
            (new IntField('stock', 'stock'))->addFlags(new ApiAware(), new Required())->setDescription('Indicates the number of blogs available.'),

            (new ListField('variation', 'variation', StringField::class))->addFlags(new Runtime(['options.name', 'options.group.name']))->setDescription('Internal field.'),
            (new StringField('display_group', 'displayGroup'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Runtime field, cannot be used as part of the criteria.'),
            (new VariantListingConfigField('variant_listing_config', 'variantListingConfig'))->addFlags(new Inherited())->setDescription('Information regarding if this variant should included in listing or not.'),
            (new JsonField('variant_restrictions', 'variantRestrictions'))->setDescription('Configuration about which variants and its combination are not available like red shirt in medium size in not available.'),
            (new IntField('guarantee_months', 'guaranteeMonths'))->addFlags(new ApiAware(), new Inherited())->setDescription('Commercial durability guarantee duration in months.'),
            (new BoolField('guarantee_confirmed', 'guaranteeConfirmed'))->addFlags(new ApiAware(), new Inherited())->setDescription('Merchant confirmation that the producer commercial durability guarantee information is accurate and has been made available.'),
            (new StringField('guarantee_terms_url', 'guaranteeTermsUrl', 2048))->addFlags(new ApiAware(), new Inherited())->setDescription('URL of the producer commercial durability guarantee terms.'),
            (new IntField('purchase_steps', 'purchaseSteps', 1))->addFlags(new ApiAware(), new Inherited())->setDescription('Specifies the scales in which the item is to be offered. For example, a scale of 2 means that your users can purchase 2, 4, 6 blogs, etc., but not 1, 3 or 5.'),
            (new IntField('max_purchase', 'maxPurchase'))->addFlags(new ApiAware(), new Inherited())->setDescription('Maximum number of items that can be purchased.'),
            (new IntField('min_purchase', 'minPurchase', 1))->addFlags(new ApiAware(), new Inherited())->setDescription('Minimum number of items that can be purchased.'),
            (new FloatField('purchase_unit', 'purchaseUnit'))->addFlags(new ApiAware(), new Inherited())->setDescription('Quantity of the item purchased. For example, 500ml, 2kg, etc.'),
            (new FloatField('reference_unit', 'referenceUnit'))->addFlags(new ApiAware(), new Inherited())->setDescription('Price of purchased item calculated as per the reference unit. Say, you bought 500ml of milk and the price is calculated in reference to 1000ml.'),
            (new BoolField('shipping_free', 'shippingFree'))->addFlags(new ApiAware(), new Inherited())->setDescription('Indicates weather the shipping price is free or not.'),
            (new PriceField('purchase_prices', 'purchasePrices'))->addFlags(new Inherited())->setDescription('Actual/cost price of the blog.'),
            (new BoolField('mark_as_topseller', 'markAsTopseller'))->addFlags(new ApiAware(), new Inherited())->setDescription('Indicates weather the blog is top seller or not.'),
            (new FloatField('weight', 'weight'))->addFlags(new ApiAware(), new Inherited())->setDescription('The weight of the blog.'),
            (new FloatField('width', 'width'))->addFlags(new ApiAware(), new Inherited())->setDescription('The width of the blog.'),
            (new FloatField('height', 'height'))->addFlags(new ApiAware(), new Inherited())->setDescription('The height of the blog.'),
            (new FloatField('length', 'length'))->addFlags(new ApiAware(), new Inherited())->setDescription('The length of the blog.'),
            (new DateTimeField('release_date', 'releaseDate'))->addFlags(new ApiAware(), new Inherited())->setDescription('The release date of a blog or blog model. This can be used to distinguish the exact variant of a blog.'),
            (new FloatField('rating_average', 'ratingAverage'))->addFlags(new ApiAware(), new WriteProtected(), new Inherited())->setDescription('Average of all the ratings.'),
            (new ListField('category_tree', 'categoryTree', IdField::class))->addFlags(new ApiAware(), new Inherited(), new WriteProtected())->setDescription('Internal field.'),
            (new ManyToManyIdField('property_ids', 'propertyIds', 'properties'))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of property.'),
            (new ManyToManyIdField('option_ids', 'optionIds', 'options'))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of options.'),
            (new ManyToManyIdField('stream_ids', 'streamIds', 'streams'))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of stream.'),
            (new ManyToManyIdField('tag_ids', 'tagIds', 'tags'))->addFlags(new Inherited(), new ApiAware())->setDescription('Unique identity of tags.'),
            (new ManyToManyIdField('category_ids', 'categoryIds', 'categories'))->addFlags(new ApiAware(), new Inherited())->setDescription('Unique identity of category.'),
            (new ChildCountField())->addFlags(new ApiAware()),
            (new BoolField('custom_field_set_selection_active', 'customFieldSetSelectionActive'))->addFlags(new Inherited())->setDescription('When boolean value is `true`, the customFieldSetSelection for blogs gets enabled.'),
            (new IntField('sales', 'sales'))->addFlags(new ApiAware(), new WriteProtected())->setDescription('Frequency of the blog sales.'),

            (new TranslatedField('metaDescription'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('name', true))->addFlags(new ApiAware(), new Inherited(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new TranslatedField('keywords'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('description'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('descriptionTeaser'))->addFlags(new ApiAware(), new Inherited())->setDescription('Read-only, HTML-stripped excerpt of the description, derived on write.'),
            (new TranslatedField('metaTitle'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('packUnit'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('packUnitPlural'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('customFields'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('slotConfig'))->addFlags(new Inherited()),
            (new TranslatedField('ogTitle'))->addFlags(new ApiAware(), new Inherited()),
            (new TranslatedField('ogDescription'))->addFlags(new ApiAware(), new Inherited()),

            // associations
            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware())->setDescription('Unique identity of the blog.'),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware())->setDescription('Blog variants that inherit from this parent blog'),

            (new ManyToOneAssociationField('tax', 'tax_id', TaxDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited())->setDescription('Tax configuration (rate and calculation rules)'),

            (new ManyToOneAssociationField('unit', 'unit_id', UnitDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited())->setDescription('Blog unit of measure (e.g., piece, liter, kg)'),

            (new ManyToOneAssociationField('cover', 'blog_media_id', BlogMediaDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited())->setDescription('Main blog image displayed in listings and detail pages'),

            (new ManyToOneAssociationField('openGraphMedia', 'open_graph_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware(), new Inherited())->setDescription('Open Graph image for social media sharing'),

            (new ManyToOneAssociationField('guaranteeTermsMedia', 'guarantee_terms_media_id', MediaDefinition::class, 'id', false))->addFlags(new ApiAware(), new Inherited())->setDescription('Document with the producer commercial durability guarantee terms'),

            (new ManyToOneAssociationField('cmsPage', 'cms_page_id', CmsPageDefinition::class, 'id', false))->addFlags(new ApiAware(), new Inherited())->setDescription('Custom CMS page layout for the blog detail page'),

            (new ManyToOneAssociationField('canonicalBlog', 'canonical_product_id', BlogDefinition::class, 'id'))->addFlags(new ApiAware(), new Inherited())->setDescription('Canonical blog reference for variant consolidation and SEO purposes'),

            (new OneToManyAssociationField('media', BlogMediaDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited())->setDescription('Blog images and media gallery'),

            (new OneToManyAssociationField('visibilities', BlogVisibilityDefinition::class, 'blog_id'))->addFlags(new CascadeDelete(), new Inherited()),

            (new OneToManyAssociationField('searchKeywords', BlogSearchKeywordDefinition::class, 'blog_id'))->addFlags(new CascadeDelete(false)),

            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited())->setDescription('Primary category assignments per sales channel for SEO and navigation'),

            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'foreign_key'))->addFlags(new ApiAware())->setDescription('SEO-friendly URLs for the blog across different sales channels'),

            (new OneToManyAssociationField('wishlists', UserWishlistBlogDefinition::class, 'blog_id'))->addFlags(new CascadeDelete()),

            (new ManyToManyAssociationField('categories', CategoryDefinition::class, BlogCategoryDefinition::class, 'blog_id', 'category_id'))->addFlags(new ApiAware(), new CascadeDelete(), new Inherited())->setDescription('Categories this blog is assigned to'),

            (new ManyToManyAssociationField('streams', ContentStreamDefinition::class, ContentStreamMappingDefinition::class, 'blog_id', 'content_stream_id'))->addFlags(new ApiAware(), new CascadeDelete())->setDescription('Dynamic blog streams this blog belongs to based on defined filters'),

            (new ManyToManyAssociationField('categoriesRo', CategoryDefinition::class, BlogCategoryTreeDefinition::class, 'blog_id', 'category_id'))->addFlags(new ApiAware(), new CascadeDelete(false), new WriteProtected())->setDescription('Read-only category tree including all parent categories for optimized queries'),

            (new ManyToManyAssociationField('tags', TagDefinition::class, BlogTagDefinition::class, 'blog_id', 'tag_id'))->addFlags(new CascadeDelete(), new Inherited(), new ApiAware())->setDescription('Tags for organizing and filtering blogs'),

            (new ManyToManyAssociationField('customFieldSets', CustomFieldSetDefinition::class, BlogCustomFieldSetDefinition::class, 'blog_id', 'custom_field_set_id'))->addFlags(new CascadeDelete(), new Inherited()),

            (new TranslationsAssociationField(BlogTranslationDefinition::class, 'blog_id'))->addFlags(new ApiAware(), new Inherited(), new Required()),
        ]);

        if (Feature::isActive('v6.8.0.0')) {
            $fields->add(
                (new StringField('type', 'type'))->addFlags(new ApiAware(), new Immutable(), new Required(), new Choice([
                    self::TYPE_PHYSICAL,
                    self::TYPE_DIGITAL,
                ]))->setDescription('The type of the blog, e.g., physical or digital.'),
            );
        } else {
            $fields->add(
                (new StringField('type', 'type'))->addFlags(new ApiAware(), new Immutable(), new Choice([
                    self::TYPE_PHYSICAL,
                    self::TYPE_DIGITAL,
                ]))->setDescription('The type of the blog, e.g., physical or digital.'),
            );

            $fields->add(
                (new ListField('states', 'states', StringField::class))
                    ->addFlags(new ApiAware(), new WriteProtected(), new Deprecated('v6.7.6.0', 'v6.8.0.0', 'type'))->setDescription('Internal field.'),
            );
        }

        return $fields;
    }
}

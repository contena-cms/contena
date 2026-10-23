<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSelling;

use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingTranslation\BlogCrossSellingTranslationDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ContentStream\ContentStreamDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogCrossSellingDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_cross_selling';
    final public const SORT_BY_PRICE = 'cheapestPrice';
    final public const SORT_BY_RELEASE_DATE = 'releaseDate';
    final public const SORT_BY_NAME = 'name';
    final public const TYPE_CONTENT_STREAM = 'contentStream';
    final public const TYPE_BLOG_LIST = 'blogList';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogCrossSellingEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogCrossSellingCollection::class;
    }

    public function getDefaults(): array
    {
        return [
            'position' => 0,
            'sortBy' => self::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'type' => self::TYPE_CONTENT_STREAM,
            'active' => false,
            'limit' => 24,
        ];
    }

    public function since(): ?string
    {
        return '6.1.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogCrossSellingHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required())->setDescription('Unique identity of blog cross selling.'),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new Required()),
            (new IntField('position', 'position', 0))->addFlags(new ApiAware(), new Required())->setDescription('The order of the tabs of your defined cross-selling actions in the frontend by entering numerical values like 1,2,3, etc.'),
            (new StringField('sort_by', 'sortBy'))->addFlags(new ApiAware())->setDescription('To sort the display of blogs by name, price or publication (descending, ascending) date.'),
            (new StringField('sort_direction', 'sortDirection'))->addFlags(new ApiAware())->setDescription('To sort the display of blogs by ascending or descending order.'),
            (new StringField('type', 'type'))->addFlags(new ApiAware(), new Required())->setDescription('Type of blog assignment for cross-selling. It can either be Dynamic blog group or Manual assignment.'),
            (new BoolField('active', 'active'))->addFlags(new ApiAware())->setDescription('When set to active, the cross-selling feature is enabled.'),
            (new IntField('limit', 'limit', 0))->addFlags(new ApiAware())->setDescription('The maximum number of blogs to be displayed in cross-selling on the item detail page of your item.'),

            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new Required())->setDescription('Unique identity of blog.'),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class))->addFlags(new ReverseInherited('crossSellings')),

            (new FkField('content_stream_id', 'contentStreamId', ContentStreamDefinition::class))->setDescription('Unique identity of blog stream.'),
            new ManyToOneAssociationField('contentStream', 'content_stream_id', ContentStreamDefinition::class),
            (new OneToManyAssociationField('assignedBlogs', BlogCrossSellingAssignedBlogsDefinition::class, 'cross_selling_id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(BlogCrossSellingTranslationDefinition::class, 'blog_cross_selling_id'))->addFlags(new ApiAware(), new Required()),
        ]);
    }
}

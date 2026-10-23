<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs;

use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
class BlogCrossSellingAssignedBlogsDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'blog_cross_selling_assigned_products';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BlogCrossSellingAssignedBlogsEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BlogCrossSellingAssignedBlogsCollection::class;
    }

    public function since(): ?string
    {
        return '6.2.0.0';
    }

    public function getHydratorClass(): string
    {
        return BlogCrossSellingAssignedBlogsHydrator::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return BlogCrossSellingDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required())->setDescription('Unique identity of Blog CrossSelling Assigned Blogs.'),
            (new FkField('cross_selling_id', 'crossSellingId', BlogCrossSellingDefinition::class))->addFlags(new Required())->setDescription('Unique identity of Blog CrossSelling.'),
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new Required())->setDescription('Unique identity of Blog.'),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class),
            new ManyToOneAssociationField('crossSelling', 'cross_selling_id', BlogCrossSellingDefinition::class),
            (new IntField('position', 'position'))->setDescription('The order of the tabs of your defined blog cross-selling in the frontend by entering numerical values like 1,2,3, etc.'),
        ]);
    }
}

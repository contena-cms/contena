<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCategoryTree;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

class BlogCategoryTreeDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'blog_category_tree';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function isVersionAware(): bool
    {
        return true;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned blog category tree assignment.'),
            new FkField('blog_id', 'blogId', BlogDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(BlogDefinition::class)->addFlags(new PrimaryKey(), new Required()),

            new FkField('category_id', 'categoryId', CategoryDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(CategoryDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, 'id', false),
        ]);
    }
}

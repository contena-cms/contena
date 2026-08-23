<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogTag;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Contena\Core\System\Tag\TagDefinition;

class BlogTagDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'blog_tag';

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
            new TenantField()->setDescription('Unique identity of the owning tenant, or null for a platform-owned blog tag assignment.'),
            new FkField('blog_id', 'blogId', BlogDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ReferenceVersionField(BlogDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new FkField('tag_id', 'tagId', TagDefinition::class)->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
            new ManyToOneAssociationField('tag', 'tag_id', TagDefinition::class, 'id', false),
        ]);
    }
}

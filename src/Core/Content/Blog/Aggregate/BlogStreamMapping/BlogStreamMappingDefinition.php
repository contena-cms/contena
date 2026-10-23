<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogStreamMapping;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * @codeCoverageIgnore
 */
class BlogStreamMappingDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'blog_stream_mapping';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('blog_stream_id', 'blogStreamId', BlogStreamDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', false),
            new ManyToOneAssociationField('blogStream', 'blog_stream_id', BlogStreamDefinition::class, 'id', false),
        ]);
    }
}

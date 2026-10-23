<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * @internal
 */
class ConsistsOfManyToManyDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'acme_consists_of_mapping';
    }

    public function since(): string
    {
        return '6.6.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('blog_id', 'blogId', BlogDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('blog_id_to', 'blogIdTo', BlogDefinition::class))->addFlags(new PrimaryKey(), new Required()),

            (new ReferenceVersionField(BlogDefinition::class))->addFlags(new PrimaryKey(), new Required(), new ApiAware()),

            (new ManyToOneAssociationField('blog', 'blog_id', BlogDefinition::class, 'id', true))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('blogTo', 'blog_id_to', BlogDefinition::class, 'id', true))->addFlags(new ApiAware()),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class BlogExtensionSelfReferenced extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new FkField('linked_product_id', 'linkedBlogId', BlogDefinition::class)
        );

        $collection->add(
            (new ReferenceVersionField(BlogDefinition::class, 'linked_product_version_id'))->addFlags(new ApiAware(), new Required())
        );

        $collection->add(
            new ManyToOneAssociationField('ManyToOneSelfReference', 'linked_product_id', BlogDefinition::class)
        );

        $collection->add(
            new ManyToOneAssociationField('ManyToOneSelfReferenceAutoload', 'linked_product_id', BlogDefinition::class, 'id', true)
        );

        $collection->add(
            new OneToManyAssociationField('oneToManySelfReferenced', BlogDefinition::class, 'linked_product_id', 'id')
        );
    }

    public function getEntityName(): string
    {
        return 'blog';
    }
}

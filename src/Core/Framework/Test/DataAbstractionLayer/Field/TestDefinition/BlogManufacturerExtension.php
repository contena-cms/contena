<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class BlogManufacturerExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToOneAssociationField('toOne', 'id', 'blog_id', ExtendedBlogManufacturerDefinition::class, false)
        );
        $collection->add(
            new OneToManyAssociationField('oneToMany', ExtendedBlogManufacturerDefinition::class, 'blog_id', 'id')
        );
    }

    public function getEntityName(): string
    {
        return BlogManufacturerDefinition::ENTITY_NAME;
    }
}

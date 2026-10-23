<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * @internal
 */
class OneToOneInheritedBlogExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'oneToOneInherited',
                'id',
                'blog_id',
                OneToOneInheritedBlogDefinition::class,
                true
            ))->addFlags(new CascadeDelete(), new Inherited())
        );
    }

    public function getEntityName(): string
    {
        return BlogDefinition::ENTITY_NAME;
    }
}

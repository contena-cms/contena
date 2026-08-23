<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\Extension;

use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
class ContentLayoutExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'content_layout_id', 'id')->addFlags(new RestrictDelete())
        );
        $collection->add(
            new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'content_layout_id', 'id')->addFlags(new RestrictDelete())
        );
    }

    public function getEntityName(): string
    {
        return ContentLayoutDefinition::ENTITY_NAME;
    }
}

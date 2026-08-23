<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\Extension;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
class ChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'channel_id', 'id')
        );
        $collection->add(
            new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'channel_id', 'id')
        );
    }

    public function getEntityName(): string
    {
        return ChannelDefinition::ENTITY_NAME;
    }
}

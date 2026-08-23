<?php declare(strict_types=1);

namespace Contena\Frontend\ContentSystem\Extension;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Frontend\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Contena\Frontend\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
class ChannelDomainExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField('headerContentLayouts', HeaderContentLayoutDefinition::class, 'domain_id', 'id')
        );
        $collection->add(
            new OneToManyAssociationField('footerContentLayouts', FooterContentLayoutDefinition::class, 'domain_id', 'id')
        );
    }

    public function getEntityName(): string
    {
        return ChannelDomainDefinition::ENTITY_NAME;
    }
}

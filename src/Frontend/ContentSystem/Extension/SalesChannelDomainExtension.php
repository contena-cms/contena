<?php declare(strict_types=1);

namespace Contena\Storefront\ContentSystem\Extension;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Contena\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Contena\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelDomainExtension extends EntityExtension
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
        return SalesChannelDomainDefinition::ENTITY_NAME;
    }
}

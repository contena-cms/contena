<?php declare(strict_types=1);

namespace Contena\Storefront\Theme\Extension;

use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Contena\Storefront\Theme\Aggregate\ThemeSalesChannelDefinition;
use Contena\Storefront\Theme\ThemeDefinition;

#[Package('discovery')]
class SalesChannelExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new ManyToManyAssociationField('themes', ThemeDefinition::class, ThemeSalesChannelDefinition::class, 'sales_channel_id', 'theme_id')
        );
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}

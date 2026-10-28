<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Tracking\Extension;

use Contena\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Contena\Core\Content\ProductExport\Tracking\SalesChannelTrackingOrderDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class SalesChannelProductExportTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            new OneToManyAssociationField(
                'salesChannelTrackingOrders',
                SalesChannelTrackingOrderDefinition::class,
                'sales_channel_id',
                'id',
            ),
        );

        $collection->add(
            new OneToManyAssociationField(
                'salesChannelTrackingCustomers',
                SalesChannelTrackingCustomerDefinition::class,
                'sales_channel_id',
                'id',
            ),
        );
    }

    public function getEntityName(): string
    {
        return SalesChannelDefinition::ENTITY_NAME;
    }
}

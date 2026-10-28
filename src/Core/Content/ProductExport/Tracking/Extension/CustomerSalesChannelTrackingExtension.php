<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Tracking\Extension;

use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Content\ProductExport\Tracking\SalesChannelTrackingCustomerDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityExtension;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 */
#[Package('discovery')]
class CustomerSalesChannelTrackingExtension extends EntityExtension
{
    public function extendFields(FieldCollection $collection): void
    {
        $collection->add(
            (new OneToOneAssociationField(
                'salesChannelTracking',
                'id',
                'customer_id',
                SalesChannelTrackingCustomerDefinition::class,
                false,
            ))->addFlags(new CascadeDelete()),
        );
    }

    public function getEntityName(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }
}

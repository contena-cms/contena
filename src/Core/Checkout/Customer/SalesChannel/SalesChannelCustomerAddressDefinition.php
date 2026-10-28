<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class SalesChannelCustomerAddressDefinition extends CustomerAddressDefinition implements SalesChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return SalesChannelCustomerAddressEntity::class;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelCustomerAddressCollection::class;
    }

    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('customerId', $context->getCustomer()?->getId()));
    }

    protected function defineFields(): FieldCollection
    {
        $fields = parent::defineFields();

        $fields->add(
            (new BoolField('is_default_billing_address', 'isDefaultBillingAddress'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.7.0'))
        );
        $fields->add(
            (new BoolField('is_default_shipping_address', 'isDefaultShippingAddress'))->addFlags(new Runtime(), new ApiAware(), new Since('6.7.7.0'))
        );

        return $fields;
    }
}

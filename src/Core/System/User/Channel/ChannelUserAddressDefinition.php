<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Since;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Channel\ChannelContext;

class ChannelUserAddressDefinition extends UserAddressDefinition implements ChannelDefinitionInterface
{
    public function getEntityClass(): string
    {
        return ChannelUserAddressEntity::class;
    }

    public function getCollectionClass(): string
    {
        return ChannelUserAddressCollection::class;
    }

    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('userId', $context->getUser()?->getId()));
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

<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<CustomerEntity, CustomerCollection>
 */
#[Package('after-sales')]
class CustomerProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return CustomerDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'salutation',
            'defaultBillingAddress.country',
            'defaultBillingAddress.countryState',
            'defaultBillingAddress.salutation',
            'defaultShippingAddress.country',
            'defaultShippingAddress.countryState',
            'defaultShippingAddress.salutation',
        ]);

        return $criteria;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<CustomerRecoveryEntity, CustomerRecoveryCollection>
 */
#[Package('after-sales')]
class CustomerRecoveryProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return CustomerRecoveryDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociation('customer.salutation');

        return $criteria;
    }
}

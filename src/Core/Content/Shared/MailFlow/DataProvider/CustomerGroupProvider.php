<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<CustomerGroupEntity, CustomerGroupCollection>
 */
#[Package('after-sales')]
class CustomerGroupProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return CustomerGroupDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}

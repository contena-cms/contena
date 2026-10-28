<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<CustomerGroupEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CustomerGroupCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'customer_group_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomerGroupEntity::class;
    }
}

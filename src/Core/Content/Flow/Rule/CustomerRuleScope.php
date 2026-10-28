<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class CustomerRuleScope extends CheckoutRuleScope
{
    public function __construct(
        private readonly CustomerEntity $customer,
        SalesChannelContext $context,
    ) {
        parent::__construct($context);
    }

    public function getCustomer(): CustomerEntity
    {
        return $this->customer;
    }
}

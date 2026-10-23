<?php declare(strict_types=1);

namespace Contena\Core\Checkout;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\RuleScope;

#[Package('checkout')]
class CheckoutRuleScope extends RuleScope
{
    public function __construct(
        protected SalesChannelContext $context
    ) {
    }

    public function getChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getUser(): ?CustomerEntity
    {
        return $this->context->getUser();
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}

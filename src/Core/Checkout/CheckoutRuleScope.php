<?php declare(strict_types=1);

namespace Contena\Core\Checkout;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\RuleScope;

#[Package('checkout')]
class CheckoutRuleScope extends RuleScope
{



    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}

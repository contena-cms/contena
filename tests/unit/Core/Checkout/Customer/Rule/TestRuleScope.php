<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class TestRuleScope extends RuleScope
{
    public function __construct(private readonly SalesChannelContext $salesChannelContext)
    {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}

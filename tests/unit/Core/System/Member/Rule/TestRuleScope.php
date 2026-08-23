<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class TestRuleScope extends RuleScope
{
    public function __construct(private readonly ChannelContext $channelContext)
    {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}

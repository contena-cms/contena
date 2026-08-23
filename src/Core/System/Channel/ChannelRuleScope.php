<?php declare(strict_types=1);

namespace Contena\Core\System\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Member\MemberEntity;

class ChannelRuleScope extends RuleScope
{
    public function __construct(
        protected ChannelContext $context
    ) {
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function getMember(): ?MemberEntity
    {
        return $this->context->getMember();
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}

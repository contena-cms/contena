<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Rule;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;

/**
 * @codeCoverageIgnore
 */
class MemberRuleScope extends ChannelRuleScope
{
    public function __construct(
        private readonly MemberEntity $member,
        ChannelContext $context,
    ) {
        parent::__construct($context);
    }

    public function getMember(): MemberEntity
    {
        return $this->member;
    }
}

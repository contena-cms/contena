<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;

/**
 * @final
 */
class MemberLoggedInRule extends Rule
{
    final public const RULE_NAME = 'memberLoggedIn';

    /**
     * @internal
     */
    public function __construct(protected bool $isLoggedIn = false)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        $member = $scope->getChannelContext()->getMember();

        $loggedIn = $member !== null;

        return $this->isLoggedIn === $loggedIn;
    }

    public function getConstraints(): array
    {
        return [
            'isLoggedIn' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->booleanField('isLoggedIn');
    }
}

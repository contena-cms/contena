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
class MemberCreatedByAdminRule extends Rule
{
    final public const RULE_NAME = 'memberCreatedByAdmin';

    /**
     * @internal
     */
    public function __construct(protected bool $shouldMemberBeCreatedByAdmin = true)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        if (!$member = $scope->getMember()) {
            return false;
        }

        return $this->shouldMemberBeCreatedByAdmin === (bool) $member->getCreatedById();
    }

    public function getConstraints(): array
    {
        return [
            'shouldMemberBeCreatedByAdmin' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->booleanField('shouldMemberBeCreatedByAdmin');
    }
}

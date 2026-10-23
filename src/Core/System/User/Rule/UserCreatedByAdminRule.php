<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class UserCreatedByAdminRule extends Rule
{
    final public const RULE_NAME = 'userCreatedByAdmin';

    /**
     * @internal
     */
    public function __construct(protected bool $shouldUserBeCreatedByAdmin = true)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$user = $scope->getUser()) {
            return false;
        }

        return $this->shouldUserBeCreatedByAdmin === (bool) $user->getCreatedById();
    }

    public function getConstraints(): array
    {
        return [
            'shouldUserBeCreatedByAdmin' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('shouldUserBeCreatedByAdmin');
    }
}

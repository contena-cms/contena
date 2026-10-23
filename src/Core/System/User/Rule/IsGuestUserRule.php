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
class IsGuestUserRule extends Rule
{
    final public const RULE_NAME = 'userIsGuest';

    /**
     * @internal
     */
    public function __construct(protected bool $isGuest = true)
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

        if ($this->isGuest) {
            return $user->getGuest();
        }

        return !$user->getGuest();
    }

    public function getConstraints(): array
    {
        return [
            'isGuest' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isGuest');
    }
}

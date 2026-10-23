<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class IsCompanyRule extends Rule
{
    final public const RULE_NAME = 'userIsCompany';

    /**
     * @internal
     */
    public function __construct(
        protected bool $isCompany = true
    ) {
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

        if ($this->isCompany) {
            return (bool) $user->getCompany();
        }

        return !$user->getCompany();
    }

    public function getConstraints(): array
    {
        return [
            'isCompany' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isCompany');
    }
}

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
class IsActiveRule extends Rule
{
    final public const RULE_NAME = 'userIsActive';

    /**
     * @internal
     */
    public function __construct(protected bool $isActive = false)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $user = $scope->getUser();
        if (!$user) {
            return false;
        }

        return $this->isActive === $user->getActive();
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isActive');
    }

    public function getConstraints(): array
    {
        return [
            'isActive' => RuleConstraints::bool(true),
        ];
    }
}

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
class UserLoggedInRule extends Rule
{
    final public const RULE_NAME = 'userLoggedIn';

    /**
     * @internal
     */
    public function __construct(protected bool $isLoggedIn = false)
    {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $user = $scope->getChannelContext()->getUser();

        $loggedIn = $user !== null;

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
        return (new RuleConfig())
            ->booleanField('isLoggedIn');
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserEntity;

/**
 * @final
 */
class IsNewsletterRecipientRule extends Rule
{
    final public const RULE_NAME = 'userIsNewsletterRecipient';

    /**
     * @internal
     */
    public function __construct(protected bool $isNewsletterRecipient = true)
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

        if ($this->isNewsletterRecipient) {
            return $this->matchIsNewsletterRecipient($user, $scope->getChannelContext());
        }

        return !$this->matchIsNewsletterRecipient($user, $scope->getChannelContext());
    }

    public function getConstraints(): array
    {
        return [
            'isNewsletterRecipient' => RuleConstraints::bool(true),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->booleanField('isNewsletterRecipient');
    }

    private function matchIsNewsletterRecipient(UserEntity $user, ChannelContext $context): bool
    {
        $channelIds = $user->getNewsletterChannelIds();

        return \is_array($channelIds) && \in_array($context->getChannelId(), $channelIds, true);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class IsNewsletterRecipientRule extends Rule
{
    final public const RULE_NAME = 'customerIsNewsletterRecipient';

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

        if (!$customer = $scope->getCustomer()) {
            return false;
        }

        if ($this->isNewsletterRecipient) {
            return $this->matchIsNewsletterRecipient($customer, $scope->getSalesChannelContext());
        }

        return !$this->matchIsNewsletterRecipient($customer, $scope->getSalesChannelContext());
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

    private function matchIsNewsletterRecipient(CustomerEntity $customer, SalesChannelContext $context): bool
    {
        $salesChannelIds = $customer->getNewsletterSalesChannelIds();

        return \is_array($salesChannelIds) && \in_array($context->getSalesChannelId(), $salesChannelIds, true);
    }
}

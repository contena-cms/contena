<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelRuleScope;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class ChannelRuleLoader implements ResetInterface
{
    private ?RuleCollection $rules = null;

    public function __construct(private readonly AbstractRuleLoader $ruleLoader)
    {
    }

    public function load(ChannelContext $context): RuleCollection
    {
        $rules = $this->loadRules($context)
            ->filterMatchingRules(new ChannelRuleScope($context));

        $context->setRuleIds(array_values($rules->getIds()));
        $context->setAreaRuleIds($rules->getIdsByArea());

        return $rules;
    }

    public function reset(): void
    {
        $this->rules = null;
    }

    private function loadRules(ChannelContext $context): RuleCollection
    {
        return $this->rules ??= $this->ruleLoader
            ->load($context->getContext())
            ->filterForContext();
    }
}

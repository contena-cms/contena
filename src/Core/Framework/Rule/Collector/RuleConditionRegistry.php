<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Collector;

use Contena\Core\Framework\Rule\FlowRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleException;

class RuleConditionRegistry
{
    /**
     * @var array<string, Rule>
     */
    private array $rules = [];

    /**
     * @internal
     *
     * @param iterable<Rule> $taggedRules
     */
    public function __construct(iterable $taggedRules)
    {
        foreach ($taggedRules as $rule) {
            $this->rules[$rule->getName()] = $rule;
        }
    }

    /**
     * @return list<string>
     */
    public function getNames(): array
    {
        return array_keys($this->rules);
    }

    public function has(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    public function getRuleInstance(string $name): Rule
    {
        if (!isset($this->rules[$name])) {
            throw RuleException::invalidCondition($name);
        }

        return $this->rules[$name];
    }

    /**
     * @return class-string<Rule>
     */
    public function getRuleClass(string $name): string
    {
        return $this->getRuleInstance($name)::class;
    }

    /**
     * @return list<string>
     */
    public function getFlowRuleNames(): array
    {
        $types = [];
        foreach ($this->rules as $rule) {
            if ($rule instanceof FlowRule) {
                $types[] = $rule->getName();
            }
        }

        return $types;
    }
}

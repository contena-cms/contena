<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule\Container;

use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Rule\CartRuleScope;
use Contena\Core\Checkout\Cart\Rule\LineItemScope;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @final
 *
 * MatchAllLineItemsRule returns true if all rules are true for all line items
 */
class MatchAllLineItemsRule extends Container
{
    final public const RULE_NAME = 'allLineItemsContainer';

    /**
     * @internal
     *
     * @param list<Rule> $rules
     * @param list<string> $types
     */
    public function __construct(
        array $rules = [],
        protected ?int $minimumShouldMatch = null,
        protected ?array $types = []
    ) {
        parent::__construct($rules);
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CartRuleScope && !$scope instanceof LineItemScope) {
            return false;
        }

        $lineItems = $scope instanceof LineItemScope
            ? new LineItemCollection([$scope->getLineItem()])
            : $scope->getCart()->getLineItems();

        if ($lineItems->count() === 0) {
            return false;
        }

        $flatItems = $this->filterAndFlatten($lineItems);

        // When there are no line items of this type, the rule still passes (e.g. "none of promotion" with an empty cart).
        if ($flatItems === []) {
            return $this->types !== null && $this->types !== [];
        }

        $context = $scope->getChannelContext();

        foreach ($this->rules as $rule) {
            $matched = 0;

            foreach ($flatItems as $lineItem) {
                $scope = new LineItemScope($lineItem, $context);
                $match = $rule->match($scope);

                if (!$this->minimumShouldMatch && !$match) {
                    return false;
                }

                if ($match) {
                    ++$matched;
                }
            }

            if ($this->minimumShouldMatch && $matched < $this->minimumShouldMatch) {
                return false;
            }
        }

        return true;
    }

    public function getConstraints(): array
    {
        $rules = parent::getConstraints();

        $rules['minimumShouldMatch'] = [new Type('int')];
        $rules['types'] = [new Type('array')];

        return $rules;
    }

    /**
     * @return array<LineItem>
     */
    private function filterAndFlatten(LineItemCollection $collection): array
    {
        $flat = $collection->getFlat();

        if ($this->types === null || $this->types === []) {
            return $flat;
        }

        return array_values(array_filter(
            $flat,
            fn (LineItem $lineItem) => \in_array($lineItem->getType(), $this->types, true)
        ));
    }
}

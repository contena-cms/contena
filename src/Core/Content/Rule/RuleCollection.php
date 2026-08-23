<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @extends EntityCollection<RuleEntity>
 */
class RuleCollection extends EntityCollection
{
    public function filterMatchingRules(RuleScope $scope): self
    {
        return $this->filter(static fn (RuleEntity $rule): bool => $rule->getPayload() instanceof Rule && $rule->getPayload()->match($scope));
    }

    public function filterForContext(): self
    {
        return $this->filter(static fn (RuleEntity $rule): bool => !$rule->getAreas() || !\in_array(RuleAreas::FLOW_CONDITION_AREA, $rule->getAreas(), true));
    }

    public function filterForFlow(): self
    {
        return $this->filter(static fn (RuleEntity $rule): bool => $rule->getAreas() !== null && \in_array(RuleAreas::FLOW_AREA, $rule->getAreas(), true));
    }

    /**
     * @return array<string, list<string>>
     */
    public function getIdsByArea(): array
    {
        $idsByArea = [];
        foreach ($this->getElements() as $rule) {
            foreach ($rule->getAreas() ?? [] as $area) {
                $idsByArea[$area][$rule->getId()] = $rule->getId();
            }
        }

        return array_map(array_values(...), $idsByArea);
    }

    public function sortByPriority(): void
    {
        $this->sort(static fn (RuleEntity $a, RuleEntity $b): int => $b->getPriority() <=> $a->getPriority());
    }

    public function getApiAlias(): string
    {
        return 'rule_collection';
    }

    protected function getExpectedClass(): string
    {
        return RuleEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Rule;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;

/**
 * @extends EntityCollection<RuleEntity>
 */
#[Package('fundamentals@after-sales')]
class RuleCollection extends EntityCollection
{

    public function filterForContext(): self
    {
        return $this->filter(
            static fn (RuleEntity $rule) => !$rule->getAreas() || !\in_array(RuleAreas::FLOW_CONDITION_AREA, $rule->getAreas(), true)
        );
    }

    public function filterForFlow(): self
    {
        return $this->filter(
            static fn (RuleEntity $rule) => $rule->getAreas() && \in_array(RuleAreas::FLOW_AREA, $rule->getAreas(), true)
        );
    }

    /**
     * @return array<string, string[]>
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
        $this->sort(static fn (RuleEntity $a, RuleEntity $b) => $b->getPriority() <=> $a->getPriority());
    }

    public function equals(RuleCollection $rules): bool
    {
        if ($this->count() !== $rules->count()) {
            return false;
        }

        foreach ($this->elements as $element) {
            if (!$rules->has($element->getId())) {
                return false;
            }
        }

        return true;
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

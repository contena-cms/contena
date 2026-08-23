<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\Struct\StateAwareTrait;
use Contena\Core\Framework\Struct\Struct;

/**
 * @final
 *
 * @template TEntityCollection of EntityCollection
 */
class EntitySearchResult extends Struct
{
    use StateAwareTrait;

    private readonly AggregationResultCollection $aggregations;

    private readonly int $page;

    private readonly ?int $limit;

    /**
     * @param TEntityCollection $entities
     */
    final public function __construct(
        private readonly int $total,
        private readonly EntityCollection $entities,
        ?AggregationResultCollection $aggregations,
        private readonly Criteria $criteria,
        private readonly Context $context,
    ) {
        $this->aggregations = $aggregations ?? new AggregationResultCollection();
        $this->limit = $criteria->getLimit();
        $this->page = !$this->limit ? 1 : (int) ceil((($criteria->getOffset() ?? 0) + 1) / $this->limit);
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * @return TEntityCollection
     */
    public function getEntities(): EntityCollection
    {
        return $this->entities;
    }

    public function getAggregations(): AggregationResultCollection
    {
        return $this->aggregations;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): ?int
    {
        return $this->limit;
    }

    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);

        unset($vars['criteria']);
        unset($vars['context']);
        $vars['elements'] = $vars['entities'];
        unset($vars['entities']);

        $this->convertDateTimePropertiesToJsonStringRepresentation($vars);

        return $vars;
    }

    public function getApiAlias(): string
    {
        return 'dal_entity_search_result';
    }
}

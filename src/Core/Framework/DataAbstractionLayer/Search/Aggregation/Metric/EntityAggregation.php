<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric;

use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;

/**
 * @final
 */
class EntityAggregation extends Aggregation
{
    public function __construct(
        string $name,
        string $field,
        protected readonly string $entity
    ) {
        parent::__construct($name, $field);
    }

    public function getEntity(): string
    {
        return $this->entity;
    }
}

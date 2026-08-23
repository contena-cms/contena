<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final
 */
class AvgResult extends AggregationResult
{
    public function __construct(
        string $name,
        protected float $avg
    ) {
        parent::__construct($name);
    }

    public function getAvg(): float
    {
        return $this->avg;
    }
}

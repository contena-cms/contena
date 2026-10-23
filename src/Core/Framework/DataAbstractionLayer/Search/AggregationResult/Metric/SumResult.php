<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric;

use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;

/**
 * @final
 */
class SumResult extends AggregationResult
{
    public function __construct(
        string $name,
        protected float $sum
    ) {
        parent::__construct($name);
    }

    public function getSum(): float
    {
        return $this->sum;
    }
}

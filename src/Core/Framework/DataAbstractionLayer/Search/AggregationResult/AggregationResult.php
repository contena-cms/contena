<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
abstract class AggregationResult extends Struct
{
    public function __construct(protected string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getApiAlias(): string
    {
        return $this->name . '_aggregation';
    }
}

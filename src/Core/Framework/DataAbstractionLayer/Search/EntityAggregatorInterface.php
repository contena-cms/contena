<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;

/**
 * @internal
 */
interface EntityAggregatorInterface
{
    public function aggregate(EntityDefinition $definition, Criteria $criteria, Context $context): AggregationResultCollection;
}

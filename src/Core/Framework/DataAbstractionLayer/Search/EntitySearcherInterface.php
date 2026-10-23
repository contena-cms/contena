<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @internal
 */
interface EntitySearcherInterface
{
    public function search(EntityDefinition $definition, Criteria $criteria, Context $context): IdSearchResult;
}

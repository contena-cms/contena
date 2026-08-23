<?php declare(strict_types=1);

namespace Contena\Administration\Framework\Search;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Criteria>
 */
class CriteriaCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return Criteria::class;
    }
}

<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Robots\Struct;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<DomainRuleStruct>
 */
class DomainRuleCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return DomainRuleStruct::class;
    }
}

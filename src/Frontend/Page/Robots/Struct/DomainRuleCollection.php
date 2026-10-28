<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Robots\Struct;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<DomainRuleStruct>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class DomainRuleCollection extends Collection
{
    protected function getExpectedClass(): string
    {
        return DomainRuleStruct::class;
    }
}

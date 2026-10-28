<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Api;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<FlowActionDefinition>
 */
#[Package('after-sales')]
class FlowActionCollectorResponse extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return FlowActionDefinition::class;
    }
}

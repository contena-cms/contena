<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<BusinessEventDefinition>
 */
class BusinessEventCollectorResponse extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return BusinessEventDefinition::class;
    }
}

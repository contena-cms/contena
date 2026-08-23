<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Struct\Collection;

/**
 * @template TEvent of NestedEvent = NestedEvent
 *
 * @extends Collection<TEvent>
 */
class NestedEventCollection extends Collection
{
    public function getApiAlias(): string
    {
        return 'dal_nested_event_collection';
    }

    protected function getExpectedClass(): string
    {
        /** @phpstan-ignore return.type (The base class intentionally validates all nested event subtypes.) */
        return NestedEvent::class;
    }
}

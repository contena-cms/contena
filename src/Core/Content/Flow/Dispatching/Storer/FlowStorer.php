<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Event\FlowEventAware;

abstract class FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    abstract public function store(FlowEventAware $event, array $stored): array;

    abstract public function restore(StorableFlow $storable): void;
}

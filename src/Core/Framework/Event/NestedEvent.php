<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Struct\JsonSerializableTrait;
use Symfony\Contracts\EventDispatcher\Event;

abstract class NestedEvent extends Event implements ContenaEvent
{
    use JsonSerializableTrait;

    public function getEvents(): ?NestedEventCollection
    {
        return null;
    }
}

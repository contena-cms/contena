<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

interface GenericEvent
{
    public function getName(): string;
}

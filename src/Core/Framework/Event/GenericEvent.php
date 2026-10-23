<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;

interface GenericEvent
{
    public function getName(): string;
}

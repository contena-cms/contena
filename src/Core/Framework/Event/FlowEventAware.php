<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Log\Package;

interface FlowEventAware extends ContenaEvent
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}

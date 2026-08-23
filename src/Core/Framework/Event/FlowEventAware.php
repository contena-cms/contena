<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Event\EventData\EventDataCollection;

interface FlowEventAware extends ContenaEvent
{
    public static function getAvailableData(): EventDataCollection;

    public function getName(): string;
}

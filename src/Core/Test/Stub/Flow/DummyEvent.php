<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Flow;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

class DummyEvent extends Event implements FlowEventAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'dummy.event';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }
}

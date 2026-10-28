<?php declare(strict_types=1);

namespace Contena\Core\Framework\Test\Webhook\_fixtures\BusinessEvents;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;

/**
 * @internal
 */
class InvalidTypeBusinessEvent implements FlowEventAware
{
    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('invalid', new InvalidEventType());
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }
}

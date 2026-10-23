<?php declare(strict_types=1);

namespace Contena\Core\Content\Test\Flow\fixtures;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class RawFlowEvent implements FlowEventAware
{
    public function __construct(protected ?Context $context = null)
    {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }

    public function getName(): string
    {
        return 'raw_flow.event';
    }

    public function getContext(): Context
    {
        return $this->context ?? Context::createDefaultContext();
    }
}

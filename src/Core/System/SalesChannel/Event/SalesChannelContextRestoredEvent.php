<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelContextRestoredEvent extends NestedEvent
{
    public function __construct(
        private readonly SalesChannelContext $restoredContext,
        private readonly SalesChannelContext $currentContext
    ) {
    }

    public function getRestoredSalesChannelContext(): SalesChannelContext
    {
        return $this->restoredContext;
    }

    public function getContext(): Context
    {
        return $this->restoredContext->getContext();
    }

    public function getCurrentSalesChannelContext(): SalesChannelContext
    {
        return $this->currentContext;
    }
}

<?php

declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Event;

use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CookieGroupCollectEvent implements ContenaSalesChannelEvent
{
    public function __construct(
        public CookieGroupCollection $cookieGroupCollection,
        public readonly Request $request,
        protected readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}

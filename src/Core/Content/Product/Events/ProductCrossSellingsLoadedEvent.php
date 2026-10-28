<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Events;

use Contena\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductCrossSellingsLoadedEvent extends Event implements ContenaSalesChannelEvent
{
    public function __construct(
        private readonly CrossSellingElementCollection $result,
        private readonly SalesChannelContext $salesChannelContext
    ) {
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCrossSellings(): CrossSellingElementCollection
    {
        return $this->result;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SitemapGenerationStartEvent extends Event implements ContenaEvent
{
    public function __construct(
        private readonly SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }
}

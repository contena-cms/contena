<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog\QuickView;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class MinimalQuickViewPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected MinimalQuickViewPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): MinimalQuickViewPage
    {
        return $this->page;
    }
}

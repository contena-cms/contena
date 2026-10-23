<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Country;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class CountryStateDataPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected CountryStateDataPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): CountryStateDataPagelet
    {
        return $this->pagelet;
    }
}

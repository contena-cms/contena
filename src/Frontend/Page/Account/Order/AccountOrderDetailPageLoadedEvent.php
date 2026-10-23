<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.8.0 - Will be removed without replacement
 */
class AccountOrderDetailPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountOrderDetailPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        parent::__construct($channelContext, $request);
    }

    public function getPage(): AccountOrderDetailPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return $this->page;
    }
}

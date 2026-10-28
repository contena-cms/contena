<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Captcha;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class BasicCaptchaPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected BasicCaptchaPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): BasicCaptchaPagelet
    {
        return $this->pagelet;
    }
}

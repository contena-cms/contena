<?php declare(strict_types=1);

namespace Contena\Storefront\Framework\Routing;

use Contena\Core\Framework\Event\BeforeSendResponseEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\SalesChannelRequest;

/**
 * @internal
 */
#[Package('discovery')]
class CanonicalLinkListener
{
    public function __invoke(BeforeSendResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        if ($canonical = $event->getRequest()->attributes->get(SalesChannelRequest::ATTRIBUTE_CANONICAL_LINK)) {
            \assert(\is_string($canonical));
            $canonical = \sprintf('<%s>; rel="canonical"', $canonical);
            $event->getResponse()->headers->set('Link', $canonical);
        }
    }
}

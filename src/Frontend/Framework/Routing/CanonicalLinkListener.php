<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\Framework\Event\BeforeSendResponseEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\ChannelRequest;

/**
 * @internal
 */
class CanonicalLinkListener
{
    public function __invoke(BeforeSendResponseEvent $event): void
    {
        if (!$event->getResponse()->isSuccessful()) {
            return;
        }

        if ($canonical = $event->getRequest()->attributes->get(ChannelRequest::ATTRIBUTE_CANONICAL_LINK)) {
            \assert(\is_string($canonical));
            $canonical = \sprintf('<%s>; rel="canonical"', $canonical);
            $event->getResponse()->headers->set('Link', $canonical);
        }
    }
}

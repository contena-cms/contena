<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Event\BeforeSendResponseEvent;

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

<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Cache;

use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Contena\Core\Framework\Adapter\Session\SessionFactory;
use Contena\Core\Framework\Adapter\Session\StatefulFlashBag;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;

/**
 * @internal
 */
class CacheCookieEventSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly SessionFactoryInterface $sessionFactory)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheCookieEvent::class => 'passCacheForFlashMessages',
            CacheHashRequiredExtension::NAME . '.post' => 'onRequireCacheHash',
        ];
    }

    public function onRequireCacheHash(CacheHashRequiredExtension $extension): void
    {
        $flashBag = $this->getStateFullFlashBagIfExists();

        if (!$flashBag) {
            return;
        }

        if ($flashBag->hasAnyFlashes() || $flashBag->displayedAnyFlashes()) {
            $extension->result = true;
        }
    }

    public function passCacheForFlashMessages(HttpCacheCookieEvent $cookieEvent): void
    {
        $flashBag = $this->getStateFullFlashBagIfExists();

        if (!$flashBag) {
            return;
        }

        // if flashbag is filled still when the response is sent, we need to pass the cache also for further requests
        if ($flashBag->hasAnyFlashes()) {
            $cookieEvent->isCacheable = false;

            return;
        }

        // if flashbag was filled before, but is empty now that means that the response contains flash messages
        // therefore we cannot store the response in the cache
        // however in general the cache should be used for the next requests
        if ($flashBag->displayedAnyFlashes()) {
            $cookieEvent->doNotStore = true;
        }
    }

    private function getStateFullFlashBagIfExists(): ?StatefulFlashBag
    {
        if (!$this->sessionFactory instanceof SessionFactory) {
            return null;
        }

        return $this->sessionFactory->getFlashBag();
    }
}

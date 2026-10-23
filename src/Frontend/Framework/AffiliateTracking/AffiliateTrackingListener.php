<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\AffiliateTracking;

use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class AffiliateTrackingListener implements EventSubscriberInterface
{
    final public const AFFILIATE_CODE_KEY = OrderService::AFFILIATE_CODE_KEY;
    final public const CAMPAIGN_CODE_KEY = OrderService::CAMPAIGN_CODE_KEY;

    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheKeyEvent::class => 'disableCacheForAffiliateTracking',
            KernelEvents::CONTROLLER => [
                ['checkAffiliateTracking', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE_POST],
            ],
        ];
    }

    public function disableCacheForAffiliateTracking(HttpCacheKeyEvent $event): void
    {
        if (!$this->hasAffiliateTracking($event->request)) {
            return;
        }

        $event->isCacheable = false;
    }

    public function checkAffiliateTracking(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        /** @var list<string> $scopes */
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        // Only process frontend routes
        if (!\in_array(FrontendRouteScope::ID, $scopes, true)) {
            return;
        }

        if (!$this->hasAffiliateTracking($request)) {
            return;
        }

        $request->attributes->set(PlatformRequest::ATTRIBUTE_NO_STORE, true);

        /** @phpstan-ignore contena.unsafeRequestHasSession (using $skipIfUninitialized = false as session will be started intentionally later; this can take the PHP session lock and is limited to affiliate tracking storing codes in the frontend session.) */
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        $affiliateCode = $request->query->get(self::AFFILIATE_CODE_KEY);
        $campaignCode = $request->query->get(self::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $session->set(self::AFFILIATE_CODE_KEY, $affiliateCode);
        }

        if ($campaignCode) {
            $session->set(self::CAMPAIGN_CODE_KEY, $campaignCode);
        }
    }

    private function hasAffiliateTracking(Request $request): bool
    {
        return (bool) $request->query->get(self::AFFILIATE_CODE_KEY)
            || (bool) $request->query->get(self::CAMPAIGN_CODE_KEY);
    }
}

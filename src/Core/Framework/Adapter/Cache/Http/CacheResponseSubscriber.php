<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-import-type CacheAttributeArray from \Contena\Core\Framework\Adapter\Cache\Http\CacheAttribute
 * @phpstan-import-type CacheAttributeType from \Contena\Core\Framework\Adapter\Cache\Http\CacheAttribute
 */
class CacheResponseSubscriber implements EventSubscriberInterface
{
    private const POLICY_AREA_FRONTEND = 'frontend';
    private const POLICY_AREA_STORE_API = 'channel_api';

    /**
     * Tells clients which query parameter differences may be ignored when matching a request against
     * an already stored response, both in the HTTP cache and in the Speculation Rules prefetch/prerender cache.
     */
    private const HEADER_NO_VARY_SEARCH = 'No-Vary-Search';


    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
        ];
    }

    public function setResponseCache(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $request = $event->getRequest();
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        if (!$context instanceof ChannelContext) {
            return;
        }

        $this->cacheHeadersService->applyCacheHeaders($context, $response);

        $area = $this->isChannelApi($request) ? self::POLICY_AREA_STORE_API : self::POLICY_AREA_FRONTEND;

        if (!$this->httpCacheEnabled) {
            // no-store attribute still has to be processed even in early return case
            if ($this->isNoStoreRoute($request)) {
                $this->applyPolicy($request, $response, $area, false, null);
            }

            return;
        }

        if (!$this->maintenanceResolver->shouldBeCached($request)) {
            $this->noCache($request, $response, $area);

            return;
        }

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            // 404 pages should not be cached by reverse proxy, as the cache hit rate would be super low,
            // and there is no way to invalidate once the url becomes available
            // To still be able to serve 404 pages fast, we don't load the full context and cache the rendered html on application side
            // as we don't have the full context the state handling is broken as no user or cart is available, even if the user is logged in
            // @see \Contena\Frontend\Framework\Routing\NotFound\NotFoundSubscriber::onError
            $this->noCache($request, $response, $area);

            return;
        }

        // Normalize attribute value to CacheAttribute instance or null
        /** @var CacheAttributeType $cacheAttributeValue */
        $cacheAttributeValue = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        $cacheAttribute = CacheAttribute::fromAttributeValue($cacheAttributeValue);

        // Preventing applying cache headers to the routes that are marked for caching, but feature flag is disabled
        if ($area === self::POLICY_AREA_STORE_API && !Feature::isActive('CACHE_REWORK')) {
            // a request resolved from the frontend session changes the page's context, so the page's cache cookies follow it
            if ($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION)) {
                $cart = $this->cartService->getCart($context->getToken(), $context);

                if (!Feature::isActive('PERFORMANCE_TWEAKS')) {
                    $this->updateSystemState($cart, $context, $request, $response);
                }

                $this->cacheHeadersService->applyCacheHash($request, $context, $cart, $response);
            }

            $this->noCache($request, $response, $area);

            return;
        }

        $route = $request->attributes->get('_route');
        /** @phpstan-ignore contena.frontendRouteUsage (Do not use Frontend routes in the core. Will be fixed with https://github.com/contena-cms/contena/issues/12968) */
        if ($route === 'frontend.checkout.configure') {
            if (!Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
                $this->setCurrencyCookie($request, $response);
            }
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);

        /** @deprecated tag:v6.8.0 - states can be removed */
        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            $states = [];
        } else {
            $states = $this->updateSystemState($cart, $context, $request, $response);
        }

        // The cache hash reflects the internal state of the context to properly cache pages
        // when multiple permutations exist (e.g. different currencies etc)
        // therefore, it needs to be applied to every request (including POST), especially when POST-requests mutate the context,
        // even when the response is not cached itself, so that the cache-hash on the client is updated for the next request
        //
        // It should be called here as side effects (cookie, header) should also appy for non-cacheable responses
        $cacheHashEvent = $this->cacheHeadersService->applyCacheHash($request, $context, $cart, $response);

        if (!$request->isMethod(Request::METHOD_GET)) {
            $this->noCache($request, $response, $area);

            return;
        }

        if ($cacheAttribute === null) {
            $this->noCache($request, $response, $area);

            return;
        }

        // No cache when dynamic calculation says so
        if ($cacheHashEvent && !$cacheHashEvent->shouldResponseBeCached()) {
            // Response is not cacheable because of dynamic calculation, giving a hint to the reverse proxy
            $response->headers->set(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS, '1');
            $this->noCache($request, $response, $area);

            return;
        }

        $cacheHash = $cacheHashEvent?->getHash();
        // No cache when client cache hash does not match the expected one. This protects from cache poisoning
        if (Feature::isActive('CACHE_REWORK')) {
            $clientHash = $request->headers->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE) ??
                $request->cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, '');
            $expectedHash = $cacheHash ?? '';

            if ($clientHash !== $expectedHash) {
                $response->headers->set(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS, '1');
                $this->noCache($request, $response, $area);

                return;
            }
        }

        /** @deprecated tag:v6.8.0 - can be removed when cache states are always empty */
        if (!Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            if ($this->hasInvalidationState($cacheAttribute->states ?? [], $states)) {
                $this->noCache($request, $response, $area);

                return;
            }
        }

        if (!Feature::isActive('PERFORMANCE_TWEAKS') && !Feature::isActive('CACHE_REWORK')) {
            $response->headers->set(
                HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER,
                implode(',', $cacheAttribute->states ?? [])
            );
        }

        // old behavior
        if (!Feature::isActive('CACHE_REWORK')) {
            if ($this->isNoStoreRoute($request)) {
                $this->addNoStoreHeader($request, $response);

                return;
            }

            $sMaxAge = $cacheAttribute->sMaxAge ?? $this->defaultTtl;
            $response->setSharedMaxAge($sMaxAge);

            if ($this->staleIfError !== null) {
                $response->headers->addCacheControlDirective('stale-if-error', $this->staleIfError);
            }

            if ($this->staleWhileRevalidate !== null) {
                $response->headers->addCacheControlDirective('stale-while-revalidate', $this->staleWhileRevalidate);
            }

            return;
        }

        $this->applyPolicy($request, $response, $area, true, $cacheAttribute);
    }

    public function setResponseCacheHeader(ResponseEvent $event): void
    {
        if (!$this->httpCacheEnabled) {
            return;
        }

        $response = $event->getResponse();

        $request = $event->getRequest();

        /** @var CacheAttributeType $cache */
        $cache = $request->attributes->get(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        if (!$cache) {
            return;
        }

        $response->headers->set(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER, '1');
    }

    private function noCache(Request $request, Response $response, string $area): void
    {
        if (!Feature::isActive('CACHE_REWORK')) {
            if ($this->isNoStoreRoute($request)) {
                $this->addNoStoreHeader($request, $response);
            }

            return;
        }
        $this->applyPolicy($request, $response, $area, false, null);
    }

    private function applyPolicy(Request $request, Response $response, string $area, bool $cacheable, ?CacheAttribute $cacheAttribute): void
    {
        $route = (string) $request->attributes->get('_route', '');
        $enforceNoStore = $request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE);

        $policy = $this->policyProvider->getPolicy($route, $area, $cacheable, $cacheAttribute, $enforceNoStore);

        // reset existing cache headers to avoid mixing policies, the resolved policy is the only source
        // of truth for both of them
        $response->headers->remove('cache-control');
        $response->headers->remove(self::HEADER_NO_VARY_SEARCH);

        // apply resolved policy to response
        $response->setCache($policy->cacheControl->toArray());

        // `No-Vary-Search` only has a meaning for responses a client may actually store, an uncacheable
        // response has nothing to match a later request against
        if ($cacheable && $policy->noVarySearch !== null) {
            $response->headers->set(self::HEADER_NO_VARY_SEARCH, $policy->noVarySearch);
        }
    }

    /**
     * @param list<string> $cacheStates
     * @param list<string> $states
     */
    private function hasInvalidationState(array $cacheStates, array $states): bool
    {
        foreach ($states as $state) {
            if (\in_array($state, $cacheStates, true)) {
                return true;
            }
        }

        return false;
    }



    /**
     * @param array<string, int|bool> $states
     *
     * @return array<string, int|bool>
     */
    private function switchState(array $states, string $key, bool $match): array
    {
        if ($match) {
            $states[$key] = true;

            return $states;
        }

        unset($states[$key]);

        return $states;
    }

    /**
     * @deprecated tag:v6.8.0 - can be removed when currency cookie is removed
     */
    private function setCurrencyCookie(Request $request, Response $response): void
    {
        $currencyId = RequestParamHelper::get($request, ChannelContextService::CURRENCY_ID);

        if (!$currencyId) {
            return;
        }

        $cookie = Cookie::create(HttpCacheKeyGenerator::CURRENCY_COOKIE, $currencyId);
        $cookie->setSecureDefault($request->isSecure());

        $response->headers->setCookie($cookie);
    }

    private function isChannelApi(Request $request): bool
    {
        return \in_array(
            ChannelApiRouteScope::ID,
            (array) $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []),
            true
        );
    }

    private function isNoStoreRoute(Request $request): bool
    {
        return $request->attributes->has(PlatformRequest::ATTRIBUTE_NO_STORE);
    }

    private function addNoStoreHeader(Request $request, Response $response): void
    {
        if (!$this->isNoStoreRoute($request)) {
            return;
        }

        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');
        $response->setExpires(new \DateTime('@0'));
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class CacheHeadersService
{
    /**
     * @param array<string> $cookies
     *
     * @internal
     */
    public function __construct(
        private readonly ExtensionDispatcher $extensions,
        private readonly CacheRelevantRulesResolver $ruleResolver,
        private readonly array $cookies,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function applyCacheHeaders(ChannelContext $context, Response $response): void
    {
        $response->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $context->getLanguageId());
        $response->headers->set(PlatformRequest::HEADER_CURRENCY_ID, $context->getCurrencyId());

        $newVaryArray = array_merge($response->getVary(), HttpCacheVariantHeaders::HEADERS);
        $newVaryArray = array_unique(array_map(static fn (string $v) => \trim($v), $newVaryArray));

        $response->setVary($newVaryArray);
    }

    public function applyCacheHash(Request $request, ChannelContext $context, Cart $cart, Response $response): ?HttpCacheCookieEvent
    {
        $isCacheHashRequired = $this->extensions->publish(
            CacheHashRequiredExtension::NAME,
            new CacheHashRequiredExtension($request, $context, $cart),
            $this->isCacheHashRequired(...),
        );

        if (!$isCacheHashRequired) {
            if ($request->cookies->has(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE)) {
                $response->headers->removeCookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
                $response->headers->clearCookie(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE);
            }

            return null;
        }

        $event = $this->buildCacheHash($request, $context);
        $newValue = $event->getHash();

        if ($request->cookies->get(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, '') !== $newValue) {
            $cookie = Cookie::create(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $newValue);
            $cookie->setSecureDefault($request->isSecure());

            $response->headers->setCookie($cookie);
        }

        $response->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $newValue);

        return $event;
    }

    private function buildCacheHash(Request $request, ChannelContext $context): HttpCacheCookieEvent
    {
        $ruleAreas = $this->ruleResolver->resolveRuleAreas($request, $context);

        if (Feature::isActive('PERFORMANCE_TWEAKS') || Feature::isActive('CACHE_REWORK')) {
            $ruleIds = $context->getRuleIdsByAreas($ruleAreas);
        } else {
            $ruleIds = $context->getRuleIds();
        }

        $ruleIds = array_unique($ruleIds);
        sort($ruleIds);

        // Must not depend on the route scope: frontend and Store API share the sw-cache-hash cookie on a domain,
        // so a Store API client on a frontend page would otherwise overwrite the frontend's cookie on every call.
        $parts = [
            HttpCacheCookieEvent::RULE_IDS => $ruleIds,
            HttpCacheCookieEvent::VERSION_ID => $context->getVersionId(),
            HttpCacheCookieEvent::CURRENCY_ID => $context->getCurrencyId(),
            HttpCacheCookieEvent::TAX_STATE => $context->getTaxState(),
            HttpCacheCookieEvent::LOGGED_IN_STATE => $context->getUser() ? 'logged-in' : 'not-logged-in',
            HttpCacheCookieEvent::LANGUAGE_ID => $context->getLanguageId(),
        ];

        foreach ($this->cookies as $cookie) {
            if ($request->cookies->has($cookie)) {
                $parts[$cookie] = $request->cookies->get($cookie);
            }
        }

        $event = new HttpCacheCookieEvent($request, $context, $parts);
        $this->dispatcher->dispatch($event);

        return $event;
    }

    private function isCacheHashRequired(Request $request, ChannelContext $channelContext, Cart $cart): bool
    {
        if ($channelContext->getUser() !== null) {
            // cache hash is required for logged in users
            return true;
        }

        if ($cart->getLineItems()->count() > 0) {
            // cache hash is required for filled carts
            return true;
        }

        if ($channelContext->getCurrencyId() !== $channelContext->getChannel()->getCurrencyId()) {
            // cache hash is required for non-default currency
            return true;
        }

        // Visitor can patch context with values that change the response for the cacheable routes
        // (tax state, country, payment method, shipping method conditioned rules)
        if ($channelContext->getShippingLocation()->getCountry()->getId()
            !== $channelContext->getChannel()->getCountryId()
        ) {
            return true;
        }

        if ($channelContext->getPaymentMethod()->getId()
            !== $channelContext->getChannel()->getPaymentMethodId()
        ) {
            return true;
        }

        if ($channelContext->getShippingMethod()->getId()
            !== $channelContext->getChannel()->getShippingMethodId()
        ) {
            return true;
        }

        // sw-language-id is part of the cache key, so only a persisted context language needs the hash.
        // Frontend requests always carry the header, set from their domain.
        if ($channelContext->getLanguageId() !== $channelContext->getChannel()->getLanguageId()
            && $channelContext->getLanguageId() !== (string) $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID, '')
        ) {
            return true;
        }

        // check if cache relevant cookies are set
        foreach ($this->cookies as $cookie) {
            if ($request->cookies->has($cookie)) {
                return true;
            }
        }

        return false;
    }
}

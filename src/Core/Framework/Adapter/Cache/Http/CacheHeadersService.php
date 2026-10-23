<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
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

}

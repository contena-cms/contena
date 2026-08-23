<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
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

        $vary = array_merge($response->getVary(), [
            PlatformRequest::HEADER_ACCESS_KEY,
            PlatformRequest::HEADER_LANGUAGE_ID,
            HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
        ]);
        $vary = array_unique(array_map(static fn (string $value): string => \trim($value), $vary));

        $response->setVary($vary);
    }

    public function applyCacheHash(
        Request $request,
        ChannelContext $context,
        Response $response,
    ): ?HttpCacheCookieEvent {
        $isCacheHashRequired = $this->extensions->publish(
            CacheHashRequiredExtension::NAME,
            new CacheHashRequiredExtension($request, $context),
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
        $ruleIds = array_unique($context->getRuleIdsByAreas($ruleAreas));
        sort($ruleIds);

        $parts = [
            HttpCacheCookieEvent::RULE_IDS => $ruleIds,
            HttpCacheCookieEvent::VERSION_ID => $context->getVersionId(),
            HttpCacheCookieEvent::LOGGED_IN_STATE => $context->getMember() ? 'logged-in' : 'not-logged-in',
        ];

        if ($this->isChannelApi($request)) {
            $parts[HttpCacheCookieEvent::LANGUAGE_ID] = $context->getLanguageId();
        }

        foreach ($this->cookies as $cookie) {
            if ($request->cookies->has($cookie)) {
                $parts[$cookie] = $request->cookies->get($cookie);
            }
        }

        $event = new HttpCacheCookieEvent($request, $context, $parts);
        $this->dispatcher->dispatch($event);

        return $event;
    }

    private function isCacheHashRequired(Request $request, ChannelContext $channelContext): bool
    {
        if ($channelContext->getMember() !== null) {
            return true;
        }

        foreach ($this->cookies as $cookie) {
            if ($request->cookies->has($cookie)) {
                return true;
            }
        }

        return false;
    }

    private function isChannelApi(Request $request): bool
    {
        return \in_array(
            ChannelApiRouteScope::ID,
            $request->attributes->all(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE),
            true,
        );
    }
}

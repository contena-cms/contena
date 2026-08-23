<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Contena\Core\Framework\Util\Hasher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class HttpCacheKeyGenerator
{
    final public const string CONTEXT_CACHE_COOKIE = 'ct-cache-hash';
    /**
     * Header to hint reverse proxy that cache was dynamically bypassed (and url still can be cached for other requests).
     * This allows decreasing TTLs for `hit-for-pass` objects in reverse proxies for such cases, while keeping higher TTLs
     * for generally not-cacheable pages.
     */
    final public const string HEADER_DYNAMIC_CACHE_BYPASS = 'ct-dynamic-cache-bypass';

    /**
     * @param string[] $ignoredParameters
     *
     * @internal
     */
    public function __construct(
        private readonly string $cacheHash,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly array $ignoredParameters
    ) {
    }

    /**
     * Generates a cache key for the given request.
     * This method should return a key that must only depend on a
     * normalized version of the request URI.
     * If the same URI can have more than one representation, based on some
     * headers, use a `vary` header to indicate them, and each representation will
     * be stored independently under the same cache key.
     *
     * @return CacheKey The cache key for the given request
     */
    public function generate(Request $request, ?Response $response = null): CacheKey
    {
        $event = new HttpCacheKeyEvent($request);

        $event->add('uri', $this->getRequestUri($request));

        $event->add('hash', $this->cacheHash);

        $this->addCookies($request, $response, $event);

        $this->dispatcher->dispatch($event);

        $parts = $event->getParts();

        return new CacheKey(
            'http-cache-' . Hasher::hash(implode('|', $parts)),
            $event->isCacheable
        );
    }

    private function getRequestUri(Request $request): string
    {
        $params = $request->query->all();
        foreach (array_keys($params) as $key) {
            if (\in_array($key, $this->ignoredParameters, true)) {
                unset($params[$key]);
            }
        }
        ksort($params);
        $params = http_build_query($params);

        return \sprintf(
            '%s%s%s',
            $request->getSchemeAndHttpHost(),
            $request->getPathInfo(),
            '?' . $params
        );
    }

    private function addCookies(Request $request, ?Response $response, HttpCacheKeyEvent $event): void
    {
        if ($cacheCookie = $this->getCookieValue($request, $response, self::CONTEXT_CACHE_COOKIE)) {
            $event->add(
                self::CONTEXT_CACHE_COOKIE,
                $cacheCookie
            );

            return;
        }
    }

    /**
     * get Cookie value, if exists use response cookie value instead of request cookie value as request cookies can be overwritten by the client
     */
    private function getCookieValue(Request $request, ?Response $response, string $cookieName): ?string
    {
        if ($response) {
            $cookie = Cookie::create($cookieName);

            $responseCookies = $response->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY);

            $responseCookie = $responseCookies[$cookie->getDomain() ?? ''][$cookie->getPath()][$cookieName] ?? null;

            if ($responseCookie) {
                // if the response contains the cookie, we use it instead of the request cookie
                // as the request cookie can be overwritten by the client
                // however the response cookie is only set if it differs from the request cookie,
                // so we need to fall back to the request cookie when the response cookie is not set
                return $responseCookie->getValue();
            }
        }

        if ($request->cookies->has($cookieName)) {
            return (string) $request->cookies->get($cookieName);
        }

        return null;
    }
}

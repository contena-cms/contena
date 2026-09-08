<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Cache\Http;

use Contena\Core\PlatformRequest;

/**
 * Single source of truth for the request inputs that select a cache variant of a URL.
 *
 * Every name listed here is emitted as `Vary` on channel responses and folded into
 * the internal HTTP cache key, so the internal cache and external reverse proxies always
 * differentiate the same variants.
 *
 * @internal
 */
final class HttpCacheVariantHeaders
{
    /**
     * `ct-cache-hash` is transported as a cookie/header; the other values are plain request headers.
     */
    public const array HEADERS = [
        PlatformRequest::HEADER_ACCESS_KEY,
        PlatformRequest::HEADER_LANGUAGE_ID,
        HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE,
    ];
}

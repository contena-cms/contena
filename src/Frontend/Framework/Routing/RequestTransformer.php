<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Content\Seo\AbstractSeoResolver;
use Contena\Core\Content\Seo\ResolvedSeoUrl;
use Contena\Core\Content\Seo\SeoUrlRequestContext;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Tenant\Resolver\TenantResolution;
use Contena\Frontend\Framework\FrontendFrameworkException;
use Contena\Frontend\Framework\Routing\Struct\DomainStruct;
use Symfony\Component\HttpFoundation\Request;

class RequestTransformer implements RequestTransformerInterface
{
    /**
     * Virtual path of the "domain"
     *
     * @example
     * - `/de`
     * - `/en`
     * - {empty} - the virtual path is optional
     */
    final public const string CHANNEL_BASE_URL = 'ct-channel-base-url';

    final public const string CHANNEL_COOKIE_PATH = 'ct-channel-cookie-path';

    /**
     * Scheme + Host + port + subdir in web root
     *
     * @example
     * - `https://shop.example` - no subdir
     * - `http://localhost:8000/subdir` - with sub dir `/subdir`
     */
    final public const string CHANNEL_ABSOLUTE_BASE_URL = 'ct-channel-absolute-base-url';

    /**
     * Scheme + Host + port + subdir in web root + virtual path
     *
     * @example
     * - `https://shop.example` - no sub dir and no virtual path
     * - `https://shop.example/en` - no sub dir and virtual path `/en`
     * - `http://localhost:8000/subdir` - with sub directory `/subdir`
     * - `http://localhost:8000/subdir/de` - with sub directory `/subdir` and virtual path `/de`
     */
    final public const string FRONTEND_URL = 'ct-frontend-url';

    final public const string CHANNEL_RESOLVED_URI = 'resolved-uri';

    final public const string ORIGINAL_REQUEST_URI = 'ct-original-request-uri';

    private const array INHERITABLE_ATTRIBUTE_NAMES = [
        self::CHANNEL_BASE_URL,
        self::CHANNEL_ABSOLUTE_BASE_URL,
        self::FRONTEND_URL,
        self::CHANNEL_RESOLVED_URI,
        self::CHANNEL_COOKIE_PATH,

        PlatformRequest::ATTRIBUTE_CHANNEL_ID,
        ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST,

        ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE,
        ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID,
        ChannelRequest::ATTRIBUTE_DOMAIN_ID,

        ChannelRequest::ATTRIBUTE_THEME_ID,
        ChannelRequest::ATTRIBUTE_THEME_NAME,
        ChannelRequest::ATTRIBUTE_THEME_BASE_NAME,

        ChannelRequest::ATTRIBUTE_CANONICAL_LINK,
    ];

    private const array DOES_NOT_REQUIRE_CHANNEL = [
        '/_wdt/',
        '/_profiler/',
        '/_error/',
        '/installer',
        '/_fragment/',
        '/robots.txt',
        '/storybook/',
    ];

    /**
     * @internal
     *
     * @param array<string> $registeredApiPrefixes
     */
    public function __construct(
        private readonly RequestTransformerInterface $decorated,
        private readonly AbstractSeoResolver $resolver,
        private readonly array $registeredApiPrefixes,
        private readonly AbstractDomainLoader $domainLoader,
        private readonly TenantDefaultDomainLoader $tenantDefaultDomainLoader,
        private readonly bool $useChannelCookiePath = false
    ) {
    }

    public function transform(Request $request): Request
    {
        $request = $this->decorated->transform($request);

        if (!$this->isChannelRequired($request->getPathInfo())) {
            return $this->decorated->transform($request);
        }

        $channel = $this->findChannel($request);
        if ($channel === null) {
            // this class and therefore the "isChannelRequired" method is currently not extendable
            // which can cause problems when adding custom paths
            throw FrontendFrameworkException::channelMappingException($request->getUri());
        }

        /**
         * Use getBasePath() instead of getBaseUrl() to exclude the script name (e.g. /index.php)
         * from the absolute base url. The channel domain url never contains the script name,
         * so including it would cause the str_replace below to fail, leaving $baseUrl as the full
         * domain url instead of just the virtual path (e.g. /de).
         *
         * getBasePath() = /subdir           (directory only)
         * getBaseUrl()  = /subdir/index.php (includes script name when explicitly in the url)
         */
        $absoluteBaseUrl = $this->getSchemeAndHttpHost($request) . $request->getBasePath();
        $baseUrl = str_replace($absoluteBaseUrl, '', $channel->url);
        // if no replacement occurred, consider punycode urls
        if ($baseUrl === $channel->url) {
            $baseUrl = str_replace(
                $this->getSchemeAndAsciiHttpHost($request) . $request->getBasePath(),
                '',
                $channel->url
            );
        }

        $resolved = $this->resolveSeoUrl(
            $request,
            $baseUrl,
            $channel->languageId,
            $channel->channelId
        );

        $currentRequestUri = $request->getRequestUri();

        /**
         * - Remove "virtual" suffix of domain mapping contena.de/de
         * - To get only the host contena.de as real request uri contena.de/
         * - Resolve remaining seo url and get the real path info contena.de/outdoor => contena.de/navigation/{id}
         *
         * Possible domains
         *
         * same host, different "virtual" suffix
         * http://contena.de/de
         * http://contena.de/en
         * http://contena.de/fr
         *
         * same host, different location
         * http://contena.fr
         * http://contena.cn
         * http://contena.de
         *
         * complete different host and location
         * http://color.com
         * http://farben.de
         * http://couleurs.fr
         *
         * installation in sub directory
         * http://localhost/development/public/de
         * http://localhost/development/public/en
         * http://localhost/development/public/fr
         *
         * installation with port
         * http://localhost:8080
         * http://localhost:8080/en
         * http://localhost:8080/fr
         */
        $transformedServerVars = array_merge(
            $request->server->all(),
            ['REQUEST_URI' => rtrim($request->getBasePath(), '/') . $resolved->pathInfo]
        );

        $transformedRequest = $request->duplicate(null, null, null, null, null, $transformedServerVars);
        $transformedRequest->attributes->set(self::CHANNEL_BASE_URL, $baseUrl);
        $transformedRequest->attributes->set(self::CHANNEL_COOKIE_PATH, $this->useChannelCookiePath ? $baseUrl : '/');
        $transformedRequest->attributes->set(self::CHANNEL_ABSOLUTE_BASE_URL, rtrim($absoluteBaseUrl, '/'));
        $transformedRequest->attributes->set(
            self::FRONTEND_URL,
            $transformedRequest->attributes->get(self::CHANNEL_ABSOLUTE_BASE_URL)
            . $transformedRequest->attributes->get(self::CHANNEL_BASE_URL)
        );
        $transformedRequest->attributes->set(self::CHANNEL_RESOLVED_URI, $resolved->pathInfo);

        $transformedRequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, $channel->channelId);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST, true);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_LOCALE, $channel->locale);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID, $channel->snippetSetId);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_DOMAIN_ID, $channel->id);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_THEME_ID, $channel->themeId);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_THEME_NAME, $channel->themeName);
        $transformedRequest->attributes->set(ChannelRequest::ATTRIBUTE_THEME_BASE_NAME, $channel->parentThemeName);

        $transformedRequest->attributes->set(
            ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE,
            (bool) $channel->maintenance
        );

        $transformedRequest->attributes->set(
            ChannelRequest::ATTRIBUTE_CHANNEL_MAINTENANCE_IP_ALLOWLIST,
            $channel->maintenanceIpAllowlist
        );

        if ($resolved->canonicalPathInfo !== null) {
            $urlPath = parse_url($channel->url, \PHP_URL_PATH);
            if ($urlPath === false || $urlPath === null) {
                $urlPath = '';
            }

            $baseUrlPath = trim($urlPath, '/');
            if (\strlen($baseUrlPath) > 1 && !str_starts_with($baseUrlPath, '/')) {
                $baseUrlPath = '/' . $baseUrlPath;
            }

            $transformedRequest->attributes->set(
                ChannelRequest::ATTRIBUTE_CANONICAL_LINK,
                $this->getSchemeAndHttpHost($request) . $baseUrlPath . $resolved->canonicalPathInfo
            );
        }

        $transformedRequest->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, $channel->languageId);
        // add all headers from the original request, overrides the headers from the domain mapping if they are passed on the request directly
        $transformedRequest->headers->add($request->headers->all());
        $transformedRequest->attributes->set(self::ORIGINAL_REQUEST_URI, $currentRequestUri);

        return $transformedRequest;
    }

    /**
     * @return array<string, mixed>
     */
    public function extractInheritableAttributes(Request $sourceRequest): array
    {
        $inheritableAttributes = $this->decorated
            ->extractInheritableAttributes($sourceRequest);

        foreach (self::INHERITABLE_ATTRIBUTE_NAMES as $attributeName) {
            if (!$sourceRequest->attributes->has($attributeName)) {
                continue;
            }

            $inheritableAttributes[$attributeName] = $sourceRequest->attributes->get($attributeName);
        }

        return $inheritableAttributes;
    }

    private function isChannelRequired(string $pathInfo): bool
    {
        $pathInfo = '/' . trim($pathInfo, '/') . '/';

        foreach ($this->registeredApiPrefixes as $apiPrefix) {
            if (str_starts_with($pathInfo, '/' . $apiPrefix . '/')) {
                return false;
            }
        }

        foreach (self::DOES_NOT_REQUIRE_CHANNEL as $prefix) {
            if (str_starts_with($pathInfo, $prefix)) {
                return false;
            }
        }

        return true;
    }

    private function findChannel(Request $request): ?DomainStruct
    {
        $domains = $this->domainLoader->loadDomains();

        if ($domains->count() === 0) {
            return null;
        }

        // domain urls and request uri should be in same format, all with trailing slash
        $requestUrl = $this->getNormalizedRequestUrl($request);

        if ($this->isHttpHostPunycode($request)) {
            $asciiRequestUrl = $this->getNormalizedRequestUrl($request, false);
            $domain = $domains->get($requestUrl) ?? $domains->get($asciiRequestUrl);
            // append the trailing slash to keep the base url a full path segment (so `/de` does not match `/destination`)
            $filter = static fn (DomainStruct $candidate): bool => str_starts_with($requestUrl, $candidate->url . '/')
                || str_starts_with($asciiRequestUrl, $candidate->url . '/');
        } else {
            $domain = $domains->get($requestUrl);
            $filter = static fn (DomainStruct $candidate): bool => str_starts_with($requestUrl, $candidate->url . '/');
        }

        // direct hit
        if ($domain !== null) {
            return $domain;
        }

        // reduce shops to which base url is the beginning of the request
        $matches = $domains->filter($filter);

        if ($matches->count() === 0) {
            // Requests addressed by a tenant convention without a registered
            // channel domain (e.g. ac.contena.cn) fall back to the tenant's
            // default web channel.
            $resolution = $request->attributes->get(PlatformRequest::ATTRIBUTE_RESOLVED_TENANT_ID);
            if ($resolution instanceof TenantResolution) {
                return $this->tenantDefaultDomainLoader->load(
                    $resolution->tenantId,
                    $this->getNormalizedRequestUrl($request)
                );
            }

            return null;
        }

        // determine most matching shop base url
        $lastBaseUrl = '';
        $bestMatch = $matches->first();
        foreach ($matches as $baseUrl => $match) {
            if (mb_strlen($baseUrl) > mb_strlen($lastBaseUrl)) {
                $bestMatch = $match;
                $lastBaseUrl = $baseUrl;
            }
        }

        return $bestMatch;
    }

    private function resolveSeoUrl(Request $request, string $baseUrl, string $languageId, string $channelId): ResolvedSeoUrl
    {
        $seoPathInfo = $request->getPathInfo();

        // only remove full base url not part
        // registered domain: 'shop-dev.de/de'
        // incoming request:  'shop-dev.de/detail'
        // without leading slash, detail would be stripped
        $baseUrl = rtrim($baseUrl, '/') . '/';

        // Include query string in resolving so SEO URLs stored with query parameters
        // (e.g., "awesome-product?test=123") are matched exactly when present.
        // Use the raw QUERY_STRING server var rather than $request->getQueryString(),
        // which already normalizes (e.g. value-less keys gain a trailing `=`).
        // SeoResolver tries both the raw and normalized forms against stored seo_path_info,
        // so a stored "?test123" can still match a request like "?test123".
        $rawQueryString = (string) $request->server->get('QUERY_STRING', '');
        $queryString = $rawQueryString === '' ? null : $rawQueryString;

        if ($this->equalsBaseUrl($seoPathInfo, $baseUrl)) {
            $seoPathInfo = '';
        } elseif ($this->containsBaseUrl($seoPathInfo, $baseUrl)) {
            $seoPathInfo = mb_substr($seoPathInfo, mb_strlen($baseUrl));
        }

        // Strip the front-controller script name (e.g. `index.php`) when Symfony left it embedded
        // in the path info. This happens when the script name follows a virtual base URL such as
        // `/de/index.php/navigation/{id}` — Symfony's base-URL auto-detection requires the script
        // name to sit at the start of the request URI, fails to match it after the language prefix
        // and so leaks the script name *basename* (never the full script path) into getPathInfo().
        // Without this strip, the SEO resolver receives `index.php/navigation/{id}` and never finds
        // the canonical SEO URL, so the redirect to the SEO-friendly path is skipped.
        //
        // We use basename() because getScriptName() can include a subdirectory prefix
        // (e.g. `/sw6/public/index.php`) while Symfony only leaks the bare filename when its
        // base-url auto-detection failed to align. The comparison is case-sensitive — matches
        // Symfony/PHP behavior on POSIX hosts. The trailing `/` on the str_starts_with check
        // guards against false-positives like `/index.php-shop` slugs.
        $scriptName = basename($request->getScriptName());
        if ($scriptName !== '' && (str_starts_with($seoPathInfo, $scriptName . '/') || $seoPathInfo === $scriptName)) {
            $seoPathInfo = mb_substr($seoPathInfo, mb_strlen($scriptName));
        }

        // pathInfo is already normalized with a leading slash by the resolver
        // (see SeoResolver::resolveUrl() / EmptyPathInfoResolver::resolveUrl()).
        return $this->resolver->resolveUrl(new SeoUrlRequestContext(
            languageId: $languageId,
            channelId: $channelId,
            pathInfo: $seoPathInfo,
            queryString: $queryString,
        ));
    }

    private function getSchemeAndHttpHost(Request $request): string
    {
        return $request->getScheme() . '://' . idn_to_utf8($request->getHttpHost());
    }

    private function getSchemeAndAsciiHttpHost(Request $request): string
    {
        return $request->getScheme() . '://' . $request->getHttpHost();
    }

    private function isHttpHostPunycode(Request $request): bool
    {
        return $request->getHttpHost() !== idn_to_utf8($request->getHttpHost());
    }

    /**
     * domain urls and request uri should be in same format, all with trailing slash
     */
    private function getNormalizedRequestUrl(Request $request, bool $unicode = true): string
    {
        $schemeAndHost = $unicode === true
            ? $this->getSchemeAndHttpHost($request)
            : $this->getSchemeAndAsciiHttpHost($request);

        return rtrim($schemeAndHost . $request->getBasePath() . $request->getPathInfo(), '/') . '/';
    }

    /**
     * We add the trailing slash to the base url
     * so we have to add it to the path info too, to check if they are equal
     */
    private function equalsBaseUrl(string $seoPathInfo, string $baseUrl): bool
    {
        return $baseUrl === rtrim($seoPathInfo, '/') . '/';
    }

    /**
     * We don't have to add the trailing slash when we check if the pathInfo contains teh base url
     */
    private function containsBaseUrl(string $seoPathInfo, string $baseUrl): bool
    {
        return $baseUrl !== '' && str_starts_with($seoPathInfo, $baseUrl);
    }
}

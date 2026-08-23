<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Content\Seo\AbstractSeoResolver;
use Contena\Core\Content\Seo\ResolvedSeoUrl;
use Contena\Core\Content\Seo\SeoUrlRequestContext;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Frontend\Framework\Routing\AbstractDomainLoader;
use Contena\Frontend\Framework\Routing\Exception\ChannelMappingException;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\Struct\DomainCollection;
use Contena\Frontend\Framework\Routing\Struct\DomainStruct;
use Contena\Frontend\Framework\Routing\TenantDefaultDomainLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestTransformer::class)]
class RequestTransformerTest extends TestCase
{
    private TenantDefaultDomainLoader $tenantDefaultDomainLoader;

    protected function setUp(): void
    {
        $this->tenantDefaultDomainLoader = new TenantDefaultDomainLoader(static::createStub(Connection::class));
    }

    /**
     * @param list<string> $registeredApiPrefixes
     */
    #[DataProvider('notRequiredChannelProvider')]
    public function testChannelIsNotRequired(array $registeredApiPrefixes, string $requestUri): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);

        // should not be called as the channel is not required
        $domainLoader->expects($this->never())->method('loadDomains');

        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader, $this->tenantDefaultDomainLoader);

        $originalRequest = Request::create($requestUri);
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame($originalRequest, $transformedRequest);
    }

    public function testChannelIsRequired(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->once())->method('loadDomains')->willReturn(new DomainCollection());

        // no registered api prefixes ==> channel is always required
        $registeredApiPrefixes = [];
        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader, $this->tenantDefaultDomainLoader);

        $originalRequest = Request::create('http://contena.cn/api');

        static::expectException(ChannelMappingException::class);
        $requestTransformer->transform($originalRequest);
    }

    public function testResolverReceivesQueryStringForExactMatching(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolveUrl')
            ->with(static::callback(static function (SeoUrlRequestContext $context) use ($languageId, $channelId): bool {
                return $context->languageId === $languageId
                    && $context->channelId === $channelId
                    && $context->pathInfo === 'Main-product/SWDEMO10001'
                    && $context->queryString === 'test=123';
            }))
            ->willReturn(new ResolvedSeoUrl(pathInfo: '/detail/123', isCanonical: true));

        $domains = new DomainCollection();
        $domains->set('http://contena.cn/', DomainStruct::fromArray([
            'url' => 'http://contena.cn',
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'languageId' => $languageId,
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Frontend',
            'parentThemeName' => '',
        ]));

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('loadDomains')
            ->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader, $this->tenantDefaultDomainLoader);

        $originalRequest = Request::create('http://contena.cn/Main-product/SWDEMO10001?test=123');
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame('/detail/123', $transformedRequest->attributes->get(RequestTransformer::CHANNEL_RESOLVED_URI));
    }

    public function testResolverReceivesRawFlagQueryString(): void
    {
        // Symfony's Request::getQueryString() normalizes value-less keys: `?test123` becomes
        // `test123=`. The SEO URL resolver compares the request query against stored
        // seo_path_info verbatim, so it needs the raw form to match a stored `path?test123`.
        // The RequestTransformer reads QUERY_STRING from server vars rather than
        // getQueryString() to preserve that raw shape.
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        $capturedContext = null;
        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolveUrl')
            ->willReturnCallback(static function (SeoUrlRequestContext $context) use (&$capturedContext): ResolvedSeoUrl {
                $capturedContext = $context;

                return new ResolvedSeoUrl(pathInfo: '/detail/123', isCanonical: true);
            });

        $domains = new DomainCollection();
        $domains->set('http://contena.cn/', DomainStruct::fromArray([
            'url' => 'http://contena.cn',
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'languageId' => $languageId,
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Frontend',
            'parentThemeName' => '',
        ]));

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('loadDomains')
            ->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader, $this->tenantDefaultDomainLoader);

        $originalRequest = Request::create('http://contena.cn/Latest-Product/SW10005?test12345');
        $requestTransformer->transform($originalRequest);

        static::assertNotNull($capturedContext);
        static::assertSame('test12345', $capturedContext->queryString, 'raw QUERY_STRING preserved (not normalized to "test12345=")');
        static::assertSame('Latest-Product/SW10005', $capturedContext->pathInfo);
    }

    public function testResolverReceivesNullForEmptyQueryString(): void
    {
        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = Uuid::randomHex();
        $channelId = Uuid::randomHex();

        $capturedContext = null;
        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolveUrl')
            ->willReturnCallback(static function (SeoUrlRequestContext $context) use (&$capturedContext): ResolvedSeoUrl {
                $capturedContext = $context;

                return new ResolvedSeoUrl(pathInfo: '/foo', isCanonical: false);
            });

        $domains = new DomainCollection();
        $domains->set('http://contena.cn/', DomainStruct::fromArray([
            'url' => 'http://contena.cn',
            'id' => Uuid::randomHex(),
            'channelId' => $channelId,
            'typeId' => Uuid::randomHex(),
            'snippetSetId' => Uuid::randomHex(),
            'languageId' => $languageId,
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Frontend',
            'parentThemeName' => '',
        ]));

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('loadDomains')
            ->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader, $this->tenantDefaultDomainLoader);

        $originalRequest = Request::create('http://contena.cn/foo');
        $requestTransformer->transform($originalRequest);

        static::assertNotNull($capturedContext);
        static::assertNull($capturedContext->queryString);
    }

    /**
     * @param array<string, string> $serverVars
     */
    #[DataProvider('transformRequestProvider')]
    public function testTransformUsesBasePathInsteadOfBaseUrl(
        string $requestUrl,
        array $serverVars,
        string $domainUrl,
        string $expectedBaseUrl,
        string $expectedAbsoluteBaseUrl,
        string $expectedFrontendUrl,
        string $expectedResolvedUri
    ): void {
        $domainId = Uuid::randomHex();
        $channelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();
        $themeId = Uuid::randomHex();

        $domainKey = rtrim($domainUrl, '/') . '/';

        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturnCallback(static fn (SeoUrlRequestContext $context) => new ResolvedSeoUrl(
            pathInfo: '/' . ltrim($context->pathInfo, '/'),
            isCanonical: false,
        ));

        $domains = new DomainCollection();
        $domains->set($domainKey, DomainStruct::fromArray([
            'url' => $domainKey,
            'id' => $domainId,
            'channelId' => $channelId,
            'typeId' => 'frontend',
            'snippetSetId' => $snippetSetId,
            'languageId' => $languageId,
            'themeId' => $themeId,
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Frontend',
            'parentThemeName' => '',
        ]));

        $domainLoader = static::createStub(AbstractDomainLoader::class);
        $domainLoader->method('loadDomains')->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [ApiRouteScope::ID], $domainLoader, $this->tenantDefaultDomainLoader);

        $request = Request::create($requestUrl, 'GET', [], [], [], $serverVars);
        $transformed = $requestTransformer->transform($request);

        static::assertSame($expectedBaseUrl, $transformed->attributes->get(RequestTransformer::CHANNEL_BASE_URL));
        static::assertSame($expectedAbsoluteBaseUrl, $transformed->attributes->get(RequestTransformer::CHANNEL_ABSOLUTE_BASE_URL));
        static::assertSame($expectedFrontendUrl, $transformed->attributes->get(RequestTransformer::FRONTEND_URL));
        static::assertSame($expectedResolvedUri, $transformed->attributes->get(RequestTransformer::CHANNEL_RESOLVED_URI));
        static::assertSame($channelId, $transformed->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_ID));
        static::assertTrue($transformed->attributes->get(ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST));
        static::assertSame('/', $transformed->attributes->get(RequestTransformer::CHANNEL_COOKIE_PATH));
    }

    #[DataProvider('useChannelCookiePathProvider')]
    public function testTransformSetsChannelCookiePathAttribute(bool $useChannelCookiePath): void
    {
        $domainUrl = 'http://contena.cn/de/';

        $decorated = static::createStub(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $resolver = static::createStub(AbstractSeoResolver::class);
        $resolver->method('resolveUrl')->willReturn(new ResolvedSeoUrl(
            pathInfo: '/',
            isCanonical: false,
        ));

        $domains = new DomainCollection();
        $domains->set($domainUrl, DomainStruct::fromArray([
            'url' => $domainUrl,
            'id' => Uuid::randomHex(),
            'channelId' => Uuid::randomHex(),
            'typeId' => 'frontend',
            'snippetSetId' => Uuid::randomHex(),
            'languageId' => Uuid::randomHex(),
            'themeId' => Uuid::randomHex(),
            'maintenance' => '0',
            'maintenanceIpAllowlist' => '',
            'locale' => 'en-GB',
            'themeName' => 'Frontend',
            'parentThemeName' => '',
        ]));
        $domainLoader = static::createStub(AbstractDomainLoader::class);
        $domainLoader->method('loadDomains')->willReturn($domains);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [ApiRouteScope::ID], $domainLoader, $this->tenantDefaultDomainLoader, $useChannelCookiePath);

        $request = Request::create('http://contena.cn/de/');
        $transformed = $requestTransformer->transform($request);

        static::assertSame($useChannelCookiePath ? '/de' : '/', $transformed->attributes->get(RequestTransformer::CHANNEL_COOKIE_PATH));
    }

    /**
     * @return iterable<string, array{useChannelCookiePath: bool}>
     */
    public static function useChannelCookiePathProvider(): iterable
    {
        yield 'enabled' => [
            'useChannelCookiePath' => true,
        ];

        yield 'disabled' => [
            'useChannelCookiePath' => false,
        ];
    }

    /**
     * @return iterable<string, array{requestUrl: string, serverVars: array<string, string>, domainUrl: string, expectedBaseUrl: string, expectedAbsoluteBaseUrl: string, expectedFrontendUrl: string, expectedResolvedUri: string}>
     */
    public static function transformRequestProvider(): iterable
    {
        yield 'index.php at root' => [
            'requestUrl' => 'http://contena.cn/index.php',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php',
            ],
            'domainUrl' => 'http://contena.cn',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn',
            'expectedResolvedUri' => '/',
        ];

        yield 'index.php with virtual path and page' => [
            'requestUrl' => 'http://contena.cn/index.php/de/outdoor',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php/de/outdoor',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'index.php in subdirectory' => [
            'requestUrl' => 'http://contena.cn/public/index.php/de',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/index.php/de',
            ],
            'domainUrl' => 'http://contena.cn/public/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn/public',
            'expectedFrontendUrl' => 'http://contena.cn/public/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'normal request without index.php' => [
            'requestUrl' => 'http://contena.cn/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'punycode to punycode direct hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com',
            'serverVars' => [],
            'domainUrl' => 'http://xn--shpwre-eua5l.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedFrontendUrl' => 'http://shöpwäre.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'punycode to unicode direct hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com',
            'serverVars' => [],
            'domainUrl' => 'http://shöpwäre.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedFrontendUrl' => 'http://shöpwäre.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'punycode to punycode filter hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://xn--shpwre-eua5l.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedFrontendUrl' => 'http://shöpwäre.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'punycode to unicode filter hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://shöpwäre.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedFrontendUrl' => 'http://shöpwäre.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'virtual path before index.php' => [
            // see https://github.com/contena/contena/issues/6666
            'requestUrl' => 'http://contena.cn/de/index.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php/navigation/abc',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'virtual path before index.php in subdirectory' => [
            // see https://github.com/contena/contena/issues/6666
            'requestUrl' => 'http://contena.cn/public/de/index.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/de/index.php/navigation/abc',
            ],
            'domainUrl' => 'http://contena.cn/public/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn/public',
            'expectedFrontendUrl' => 'http://contena.cn/public/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'virtual path equals base url with index.php' => [
            // /de/index.php with no further path should resolve to the sales-channel home
            'requestUrl' => 'http://contena.cn/de/index.php',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'virtual path with trailing slash after index.php' => [
            // /de/index.php/ (trailing slash, no further path) should also resolve to home
            'requestUrl' => 'http://contena.cn/de/index.php/',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php/',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'virtual path before custom front controller (app.php)' => [
            // ensure the strip uses basename($scriptName) and works for non-index.php front controllers
            'requestUrl' => 'http://contena.cn/de/app.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/app.php',
                'SCRIPT_NAME' => '/app.php',
                'PHP_SELF' => '/de/app.php/navigation/abc',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'slug with index.php prefix is preserved (boundary guard)' => [
            // a hypothetical SEO slug like "index.php-shop" must not be mangled by the strip;
            // the `$scriptName . '/'` suffix on str_starts_with ensures only the bare script
            // basename followed by a path separator is stripped.
            'requestUrl' => 'http://contena.cn/de/index.php-shop',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php-shop',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/index.php-shop',
        ];

        yield 'slug with index.php prefix is preserved when followed by sub-path' => [
            // an "index.php-shop" parent slug with a deeper path must also pass through unchanged.
            // The `$scriptName . '/'` boundary requires an exact script-name segment, so
            // "index.php-shop/foo" cannot be partial-stripped to "-shop/foo".
            'requestUrl' => 'http://contena.cn/de/index.php-shop/foo',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php-shop/foo',
            ],
            'domainUrl' => 'http://contena.cn/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://contena.cn',
            'expectedFrontendUrl' => 'http://contena.cn/de',
            'expectedResolvedUri' => '/index.php-shop/foo',
        ];
    }

    /**
     * @return iterable<string, array{registeredApiPrefixes: list<string>, requestUri: string}>
     */
    public static function notRequiredChannelProvider(): iterable
    {
        yield 'Default case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/api',
        ];

        yield 'Case with trailing slash' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/api/',
        ];

        yield 'Case with double leading slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn//api',
        ];

        yield 'Case with double trailing slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/api//',
        ];

        yield 'Case with double leading and trailing slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn//api//',
        ];

        // Allowedlist paths:
        yield '_wdt case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/_wdt/',
        ];

        yield '_profiler case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/_profiler/',
        ];

        yield '_error case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/_error/',
        ];

        yield 'installer case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/installer',
        ];

        yield '_fragment case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://contena.cn/_fragment/',
        ];
    }
}

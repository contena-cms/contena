<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Contena\Core\Framework\Adapter\Cache\Http\CachePolicy;
use Contena\Core\Framework\Adapter\Cache\Http\CachePolicyProvider;
use Contena\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;
use Contena\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Contena\Core\Framework\Adapter\Cache\Http\DefaultPolicies;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\PlatformRequest;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\AbstractSessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 *
 * @phpstan-import-type CachePolicyConfig from CachePolicy
 * @phpstan-import-type DefaultPoliciesConfig from DefaultPolicies
 */
#[CoversClass(CacheResponseSubscriber::class)]
class CacheResponseSubscriberTest extends TestCase
{
    private const IP = '127.0.0.1';

    private CacheResponseSubscriber $subscriber;

    private CacheHeadersService&MockObject $cacheHeadersService;

    protected function setUp(): void
    {
        $this->cacheHeadersService = $this->createMock(CacheHeadersService::class);
        $this->subscriber = $this->createSubscriber();
    }

    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::RESPONSE => [
                ['setResponseCache', -1500],
                ['setResponseCacheHeader', 1500],
            ],
        ];

        static::assertSame($expected, CacheResponseSubscriber::getSubscribedEvents());
    }

    public function testNoHeadersAreSetIfCacheIsDisabled(): void
    {
        $subscriber = $this->createSubscriber(enabled: false);
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
        ]);
        $response = new Response();
        $expectedHeaders = $response->headers->all();

        $this->cacheHeadersService->expects($this->once())->method('applyCacheHeaders');
        $this->cacheHeadersService->expects($this->never())->method('applyCacheHash');

        $subscriber->setResponseCache($this->createResponseEvent($request, $response));

        static::assertSame($expectedHeaders, $response->headers->all());
    }

    public function testNoStoreAppliedWhenCacheDisabled(): void
    {
        $subscriber = $this->createSubscriber(enabled: false);
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
            PlatformRequest::ATTRIBUTE_NO_STORE => true,
        ]);
        $response = new Response();

        $this->cacheHeadersService->expects($this->once())->method('applyCacheHeaders');
        $this->cacheHeadersService->expects($this->never())->method('applyCacheHash');

        $subscriber->setResponseCache($this->createResponseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        static::assertTrue($response->headers->hasCacheControlDirective('must-revalidate'));
        static::assertFalse($response->isCacheable());
    }

    public function testNoAutoCacheControlHeader(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]);
        $event = $this->createResponseEvent($request, new Response());

        $this->subscriber->setResponseCacheHeader($event);

        static::assertSame('1', $event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderCacheDisabled(): void
    {
        $event = $this->createResponseEvent(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]),
            new Response()
        );

        $this->createSubscriber(enabled: false)->setResponseCacheHeader($event);

        static::assertNull($event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    public function testNoAutoCacheControlHeaderNoHttpCacheRoute(): void
    {
        $event = $this->createResponseEvent(
            new Request(attributes: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => false]),
            new Response()
        );

        $this->subscriber->setResponseCacheHeader($event);

        static::assertNull($event->getResponse()->headers->get(AbstractSessionListener::NO_AUTO_CACHE_CONTROL_HEADER));
    }

    /**
     * @param string[] $allowlist
     */
    #[DataProvider('maintenanceRequestProvider')]
    public function testMaintenanceRequest(bool $active, array $allowlist, bool $shouldBeCached): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
            PlatformRequest::ATTRIBUTE_MAINTENANCE => $active,
            PlatformRequest::ATTRIBUTE_MAINTENANCE_IP_ALLOWLIST => json_encode($allowlist, \JSON_THROW_ON_ERROR),
        ]);
        $request->server->set('REMOTE_ADDR', self::IP);

        $this->cacheHeadersService->expects($this->once())->method('applyCacheHeaders');
        $this->cacheHeadersService->expects($shouldBeCached ? $this->once() : $this->never())
            ->method('applyCacheHash');

        $response = new Response();
        $this->subscriber->setResponseCache($this->createResponseEvent($request, $response));

        if (!$shouldBeCached) {
            static::assertFalse($response->isCacheable());
        }
    }

    /**
     * @return iterable<string, array{active: bool, allowlist: list<string>, shouldBeCached: bool}>
     */
    public static function maintenanceRequestProvider(): iterable
    {
        yield 'maintenance inactive' => ['active' => false, 'allowlist' => [], 'shouldBeCached' => true];
        yield 'maintenance active without allowlist' => ['active' => true, 'allowlist' => [], 'shouldBeCached' => true];
        yield 'maintenance active for allowed client' => ['active' => true, 'allowlist' => [self::IP], 'shouldBeCached' => false];
        yield 'maintenance active for other client' => ['active' => true, 'allowlist' => ['120.0.0.0'], 'shouldBeCached' => true];
    }

    public function testAdminPagesNotCached(): void
    {
        $response = new Response();

        $this->cacheHeadersService->expects($this->never())->method('applyCacheHeaders');
        $this->cacheHeadersService->expects($this->never())->method('applyCacheHash');

        $this->subscriber->setResponseCache($this->createResponseEvent(
            new Request(attributes: ['_route' => 'admin.dashboard.index']),
            $response
        ));

        static::assertSame('no-cache, private', $response->headers->get('cache-control'));
    }

    public function testGetRequestGetsCached(): void
    {
        $subscriber = $this->createSubscriber(policyProvider: $this->createCachePolicyProvider(
            policiesConfig: [
                'cacheable' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 100]]],
                'uncacheable' => ['headers' => ['cache_control' => ['private' => true, 'no_store' => true]]],
            ],
            defaultPoliciesConfig: [
                'frontend' => ['cacheable' => 'cacheable', 'uncacheable' => 'uncacheable'],
            ],
        ));
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ]);
        $response = new Response();

        $this->cacheHeadersService->expects($this->once())->method('applyCacheHeaders');
        $this->cacheHeadersService->expects($this->once())->method('applyCacheHash')->willReturn(null);

        $subscriber->setResponseCache($this->createResponseEvent($request, $response));

        static::assertSame('public, s-maxage=100', $response->headers->get('cache-control'));
    }

    /**
     * @param array<string, mixed> $requestOptions
     * @param array<string, CachePolicyConfig> $policies
     * @param array<string, DefaultPoliciesConfig> $defaultPolicies
     * @param array<string, string> $routePolicies
     */
    #[DataProvider('cachePoliciesAppliedProvider')]
    public function testCachePoliciesApplied(
        array $requestOptions,
        array $policies,
        array $defaultPolicies,
        array $routePolicies,
        string $expectedCacheControl,
    ): void {
        $requestOptions[PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT] = Generator::generateChannelContext();
        $requestOptions[PlatformRequest::ATTRIBUTE_HTTP_CACHE] ??= true;

        $request = new Request(attributes: $requestOptions);
        if (isset($requestOptions['_method'])) {
            $request->setMethod($requestOptions['_method']);
        }

        $this->cacheHeadersService->expects($this->once())->method('applyCacheHeaders');
        $this->cacheHeadersService->expects($this->once())->method('applyCacheHash')->willReturn(null);

        $subscriber = $this->createSubscriber(policyProvider: $this->createCachePolicyProvider(
            $policies,
            $defaultPolicies,
            $routePolicies,
        ));
        $response = new Response();
        $subscriber->setResponseCache($this->createResponseEvent($request, $response));

        static::assertSame($expectedCacheControl, $response->headers->get('cache-control'));
    }

    /**
     * @return iterable<string, array{
     *     requestOptions: array<string, mixed>,
     *     policies: array<string, CachePolicyConfig>,
     *     defaultPolicies: array<string, DefaultPoliciesConfig>,
     *     routePolicies: array<string, string>,
     *     expectedCacheControl: string
     * }>
     */
    public static function cachePoliciesAppliedProvider(): iterable
    {
        $policies = [
            'frontend' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 100]]],
            'channel-api' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 200]]],
            'route' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 300]]],
            'uncacheable' => ['headers' => ['cache_control' => ['private' => true, 'no_cache' => true, 'max_age' => 0, 's_maxage' => 0]]],
        ];
        $defaults = [
            'frontend' => ['cacheable' => 'frontend', 'uncacheable' => 'uncacheable'],
            'channel_api' => ['cacheable' => 'channel-api', 'uncacheable' => 'uncacheable'],
        ];

        yield 'frontend policy' => [
            'requestOptions' => [],
            'policies' => $policies,
            'defaultPolicies' => $defaults,
            'routePolicies' => [],
            'expectedCacheControl' => 'public, s-maxage=100',
        ];
        yield 'channel API policy' => [
            'requestOptions' => [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]],
            'policies' => $policies,
            'defaultPolicies' => $defaults,
            'routePolicies' => [],
            'expectedCacheControl' => 'public, s-maxage=200',
        ];
        yield 'route policy overrides defaults' => [
            'requestOptions' => ['_route' => 'channel-api.blog.search'],
            'policies' => $policies,
            'defaultPolicies' => $defaults,
            'routePolicies' => ['channel-api.blog.search' => 'route'],
            'expectedCacheControl' => 'public, s-maxage=300',
        ];
        yield 'POST is not cached' => [
            'requestOptions' => ['_method' => Request::METHOD_POST],
            'policies' => $policies,
            'defaultPolicies' => $defaults,
            'routePolicies' => [],
            'expectedCacheControl' => 'max-age=0, no-cache, private, s-maxage=0',
        ];
        yield 'no-store attribute enforces no-store policy' => [
            'requestOptions' => [PlatformRequest::ATTRIBUTE_NO_STORE => true],
            'policies' => $policies,
            'defaultPolicies' => $defaults,
            'routePolicies' => [],
            'expectedCacheControl' => 'max-age=0, must-revalidate, no-cache, no-store, private',
        ];
    }

    /**
     * @param array{header?: string, cookie?: string} $clientHash
     */
    #[DataProvider('cacheHashValidationProvider')]
    public function testCacheHashValidation(
        array $clientHash,
        ?string $serviceHash,
        bool $expectCacheable,
        bool $expectBypassHeader,
    ): void {
        $cacheHeadersService = $this->createMock(CacheHeadersService::class);
        $subscriber = new CacheResponseSubscriber(
            true,
            new MaintenanceModeResolver(new EventDispatcher()),
            $cacheHeadersService,
            $this->createCachePolicyProvider(
                policiesConfig: [
                    'cacheable' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 100]]],
                    'uncacheable' => ['headers' => ['cache_control' => ['private' => true, 'no_store' => true]]],
                ],
                defaultPoliciesConfig: [
                    'frontend' => ['cacheable' => 'cacheable', 'uncacheable' => 'uncacheable'],
                ],
            ),
        );
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ]);

        if (isset($clientHash['header'])) {
            $request->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $clientHash['header']);
        }
        if (isset($clientHash['cookie'])) {
            $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, $clientHash['cookie']);
        }

        if ($serviceHash !== null) {
            $event = static::createStub(HttpCacheCookieEvent::class);
            $event->method('getHash')->willReturn($serviceHash);
            $event->method('shouldResponseBeCached')->willReturn($serviceHash !== HttpCacheCookieEvent::NOT_CACHEABLE);
            $cacheHeadersService->expects($this->once())->method('applyCacheHash')->willReturn($event);
        } else {
            $cacheHeadersService->expects($this->once())->method('applyCacheHash')->willReturn(null);
        }

        $response = new Response();
        $subscriber->setResponseCache($this->createResponseEvent($request, $response));

        static::assertSame($expectBypassHeader, $response->headers->has(HttpCacheKeyGenerator::HEADER_DYNAMIC_CACHE_BYPASS));
        static::assertSame($expectCacheable, str_contains((string) $response->headers->get('cache-control'), 'public'));
    }

    /**
     * @return iterable<string, array{
     *     clientHash: array{header?: string, cookie?: string},
     *     serviceHash: string|null,
     *     expectCacheable: bool,
     *     expectBypassHeader: bool
     * }>
     */
    public static function cacheHashValidationProvider(): iterable
    {
        yield 'no client or service hash' => [
            'clientHash' => [],
            'serviceHash' => null,
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];
        yield 'matching cookie hash' => [
            'clientHash' => ['cookie' => 'abc123'],
            'serviceHash' => 'abc123',
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];
        yield 'matching header takes precedence' => [
            'clientHash' => ['header' => 'abc123', 'cookie' => 'different'],
            'serviceHash' => 'abc123',
            'expectCacheable' => true,
            'expectBypassHeader' => false,
        ];
        yield 'not-cacheable service state' => [
            'clientHash' => ['cookie' => HttpCacheCookieEvent::NOT_CACHEABLE],
            'serviceHash' => HttpCacheCookieEvent::NOT_CACHEABLE,
            'expectCacheable' => false,
            'expectBypassHeader' => true,
        ];
        yield 'hash mismatch' => [
            'clientHash' => ['header' => 'old-hash'],
            'serviceHash' => 'new-hash',
            'expectCacheable' => false,
            'expectBypassHeader' => true,
        ];
        yield 'missing client hash' => [
            'clientHash' => [],
            'serviceHash' => 'abc123',
            'expectCacheable' => false,
            'expectBypassHeader' => true,
        ];
    }

    private function createSubscriber(
        bool $enabled = true,
        ?CachePolicyProvider $policyProvider = null,
    ): CacheResponseSubscriber {
        return new CacheResponseSubscriber(
            $enabled,
            new MaintenanceModeResolver(new EventDispatcher()),
            $this->cacheHeadersService,
            $policyProvider ?? $this->createCachePolicyProvider(),
        );
    }

    /**
     * @param array<string, CachePolicyConfig> $policiesConfig
     * @param array<string, DefaultPoliciesConfig> $defaultPoliciesConfig
     * @param array<string, string> $routePoliciesConfig
     */
    private function createCachePolicyProvider(
        array $policiesConfig = [],
        array $defaultPoliciesConfig = [],
        array $routePoliciesConfig = [],
    ): CachePolicyProvider {
        return CachePolicyProviderFactory::create($policiesConfig, $routePoliciesConfig, $defaultPoliciesConfig);
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        );
    }
}

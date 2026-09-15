<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Contena\Core\Framework\Adapter\Cache\Http\CacheHeadersService;
use Contena\Core\Framework\Adapter\Cache\Http\CachePolicyProviderFactory;
use Contena\Core\Framework\Adapter\Cache\Http\CacheResponseSubscriber;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\Framework\Routing\SessionContextTokenSubscriber;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\SessionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[CoversClass(SessionContextTokenSubscriber::class)]
class SessionContextTokenSubscriberTest extends TestCase
{
    private const BINDING_ENABLED = ['core.systemWideLoginRegistration.isMemberBoundToChannel' => true];

    public function testHasEvents(): void
    {
        static::assertSame([
            KernelEvents::REQUEST => [['startSession', 40]],
            KernelEvents::RESPONSE => [['protectSessionResolvedResponse', -1600]],
            MemberLoginEvent::class => 'onMemberLogin',
            MemberLogoutEvent::class => 'onMemberLogout',
            ChannelContextResolvedEvent::class => 'onContextResolved',
        ], SessionContextTokenSubscriber::getSubscribedEvents());
    }

    public function testStartSessionStampsTheSessionIdAndMintsAToken(): void
    {
        $request = $this->ownerRequest('channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        $session = $request->getSession();
        static::assertTrue($session->has(SessionContextTokenAccessor::SESSION_ID_KEY));

        $token = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($token);
        static::assertSame(32, \strlen($token));
        static::assertSame($token, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testStartSessionWithoutARequestOnTheStackIsIgnored(): void
    {
        $this->subscriber([])->startSession($this->requestEvent(new Request()));

        $this->expectNotToPerformAssertions();
    }

    public function testStartSessionIgnoresRequestsWithoutTheOwnerMarker(): void
    {
        $request = new Request();
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        static::assertSame(0, $factoryCalls);
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testASubRequestGetsTheMainRequestsToken(): void
    {
        $mainRequest = $this->ownerRequest('channel-a');
        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'main-token');
        $mainRequest->setSession($session);

        $subRequest = new Request();

        $this->subscriber([$mainRequest, $subRequest])
            ->startSession($this->requestEvent($subRequest, HttpKernelInterface::SUB_REQUEST));

        static::assertSame('main-token', $mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('main-token', $subRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testWithoutBindingTheTokenLivesInThePlainKeyOnly(): void
    {
        $request = $this->ownerRequest('channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        static::assertTrue($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-channel-a'));
    }

    public function testWithBindingTheTokenLivesInTheChannelKey(): void
    {
        $request = $this->ownerRequest('channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request], self::BINDING_ENABLED)->startSession($this->requestEvent($request));

        $session = $request->getSession();
        $channelToken = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-channel-a');
        static::assertNotNull($channelToken);
        static::assertSame($channelToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($channelToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN), 'the plain key mirrors the channel being browsed');
    }

    public function testWithBindingTokensSurviveSwitchingChannels(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $requestA = $this->ownerRequest('channel-a');
        $requestA->setSession($session);
        $this->subscriber([$requestA], self::BINDING_ENABLED)->startSession($this->requestEvent($requestA));
        $tokenA = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-channel-a');

        $requestB = $this->ownerRequest('channel-b');
        $requestB->setSession($session);
        $this->subscriber([$requestB], self::BINDING_ENABLED)->startSession($this->requestEvent($requestB));
        $tokenB = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-channel-b');

        static::assertNotNull($tokenA);
        static::assertNotNull($tokenB);
        static::assertNotSame($tokenA, $tokenB);

        $requestA2 = $this->ownerRequest('channel-a');
        $requestA2->setSession($session);
        $this->subscriber([$requestA2], self::BINDING_ENABLED)->startSession($this->requestEvent($requestA2));

        static::assertSame($tokenA, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-channel-a'), 'returning to a channel resumes its token');
        static::assertSame($tokenA, $requestA2->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($tokenB, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-channel-b'));
    }

    public function testLoginRotatesTheTokenAndTheSessionId(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getChannelId());
        $session = $this->sessionWithId('before-login');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'anonymous');
        $request->setSession($session);

        $this->subscriber([$request])->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

        static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('logged-in', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('before-login', $session->getId(), 'a login is a privilege boundary, the session ID must not survive it');
        static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
    }

    public function testLoginWithBindingStoresTheTokenInTheChannelKey(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getChannelId());
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request], self::BINDING_ENABLED)
            ->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

        $session = $request->getSession();
        static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $context->getChannelId()));
        static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testLogoutContinuesOnTheTokenTheLogoutRouteReturned(): void
    {
        $context = Generator::generateChannelContext(token: 'the-routes-own-token');
        $request = $this->ownerRequest($context->getChannelId());
        $session = $this->sessionWithId('logged-in-session');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'logged-in');
        $request->setSession($session);

        $this->subscriber([$request])->onMemberLogout(new MemberLogoutEvent($context, new MemberEntity()));

        static::assertSame('the-routes-own-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('the-routes-own-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('logged-in-session', $session->getId());
    }

    public function testASwappedTokenIsFollowedIntoTheSession(): void
    {
        $context = Generator::generateChannelContext(token: 'fresh');
        $request = $this->ownerRequest($context->getChannelId());
        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'expired');
        $request->setSession($session);

        $this->subscriber([$request])->onContextResolved(new ChannelContextResolvedEvent($context, 'expired'));

        static::assertSame('fresh', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('fresh', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoginAfterContextReplacementPersistsTheLatestTokenAndSessionCookie(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/contena-session-' . Uuid::randomHex();
        $filesystem->mkdir($directory);
        $session = new Session(new NativeSessionStorage(
            ['name' => 'session-', 'cache_limiter' => '', 'use_cookies' => false],
            new NativeFileSessionHandler($directory),
        ));

        try {
            $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'expired');
            $originalId = $session->getId();
            $session->save();

            $context = Generator::generateChannelContext(token: 'fresh');
            $request = new Request(cookies: ['session-' => $originalId], attributes: [
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
            ]);
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
            $request->setSession($session);
            $accessor = new SessionContextTokenAccessor(['name' => 'session-'], true, new StaticSystemConfigService(), new RouteScopeRegistry([new ChannelApiRouteScope()]));
            static::assertSame('expired', $accessor->read($request, $context->getChannelId()));

            $subscriber = $this->subscriber([$request]);
            $subscriber->onContextResolved(new ChannelContextResolvedEvent($context, 'expired'));
            $replacementId = $session->getId();
            static::assertNotSame($originalId, $replacementId);

            $subscriber->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

            static::assertTrue($session->isStarted(), 'Symfony must still emit the migrated session cookie');
            static::assertNotSame($replacementId, $session->getId());
            static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertSame('logged-in', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));

            $response = new Response();
            new SessionListener()->onKernelResponse($this->responseEvent($request, $response));
            $cookies = $response->headers->getCookies();
            static::assertCount(1, $cookies);
            static::assertSame('session-', $cookies[0]->getName());
            static::assertSame($session->getId(), $cookies[0]->getValue());
            static::assertFalse($session->isStarted());

            $nextRequest = new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]],
                cookies: ['session-' => $cookies[0]->getValue()]
            );
            $nextRequest->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
            $nextRequest->setSession($session);
            static::assertSame('logged-in', $accessor->read($nextRequest, $context->getChannelId()));
        } finally {
            if ($session->isStarted()) {
                $session->save();
            }
            $filesystem->remove($directory);
        }
    }

    public function testAnUnchangedTokenLeavesTheSessionAlone(): void
    {
        $context = Generator::generateChannelContext(token: 'current');
        $request = $this->ownerRequest($context->getChannelId());
        $session = $this->sessionWithId('stable');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'current');
        $request->setSession($session);

        $this->subscriber([$request])->onContextResolved(new ChannelContextResolvedEvent($context, 'current'));

        static::assertSame('stable', $session->getId());
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRotationWithoutARequestIsIgnored(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');

        $this->subscriber([])->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

        $this->expectNotToPerformAssertions();
    }

    public function testRotationOnAPlainChannelApiRequestLeavesTheSessionAlone(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]]);
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

        static::assertSame(0, $factoryCalls, 'a Channel API request that did not declare the session as its source never touches it');
        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAnOwnerWithoutAnInitializedSessionIsLeftAlone(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getChannelId());
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

        static::assertSame(0, $factoryCalls);
    }

    public function testTheKillSwitchDoesNotAffectTheOwner(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getChannelId());
        $request->setSession(new Session(new MockArraySessionStorage()));
        $subscriber = $this->subscriber([$request], enabled: false);

        $subscriber->startSession($this->requestEvent($request));
        static::assertNotNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $subscriber->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));
        static::assertSame('logged-in', $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testAnAdminApiRequestCannotRotateTheFrontendSession(): void
    {
        $context = Generator::generateChannelContext(token: 'rotated');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->cookies->set('session-', 'frontend-session');
        $session = $this->sessionWithId('frontend-session');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'untouched');
        $request->setSession($session);

        $this->subscriber([$request])->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'rotated'));

        static::assertSame('untouched', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('frontend-session', $session->getId());
    }

    public function testTheKillSwitchStopsBorrowers(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->cookies->set('session-', 'resumable');
        $session = $this->sessionWithId('resumable');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'anonymous');
        $request->setSession($session);

        $this->subscriber([$request], enabled: false)
            ->onMemberLogin(new MemberLoginEvent($context, new MemberEntity(), 'logged-in'));

        static::assertSame('anonymous', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('resumable', $session->getId());
    }

    public function testSessionResolvedChannelApiResponsesAreNeverSharedCacheable(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, s-maxage=1800');

        $this->subscriber([$request])->protectSessionResolvedResponse($this->responseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('private'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testSessionResolvedChannelApiResponsesOmitTheTokenHeader(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        // what ResponseHeaderListener's echo and ContextTokenResponse put there
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'the-shoppers-token');

        $this->subscriber([$request])->protectSessionResolvedResponse($this->responseEvent($request, $response));

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testOtherChannelApiResponsesKeepTheirCacheHeadersAndToken(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]]);
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, s-maxage=1800');
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'a-client-managed-token');

        $this->subscriber([$request])->protectSessionResolvedResponse($this->responseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('public'));
        static::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        static::assertSame('a-client-managed-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testResponsesOutsideTheChannelApiAreNotTouched(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        $response = new Response();
        $response->headers->set('Cache-Control', 'public, s-maxage=1800');

        $this->subscriber([$request])->protectSessionResolvedResponse($this->responseEvent($request, $response));

        static::assertTrue($response->headers->hasCacheControlDirective('public'));
    }

    /**
     * The test environment ships `contena.http.cache.enabled = 0`, so an integration test can never
     * show what happens on a route the cache would really store. This wires a cache-enabled
     * CacheResponseSubscriber next to ours, in the priority order the kernel uses, and drives a
     * genuinely cacheable Channel API route both ways.
     */
    #[DataProvider('sessionInvolvementProvider')]
    public function testSessionResolvedResponsesOverrideAnEnabledChannelApiCachePolicy(bool $fromSession, string $expected): void
    {
        $eventDispatcher = new EventDispatcher();

        $cacheHashEvent = static::createStub(HttpCacheCookieEvent::class);
        $cacheHashEvent->method('getHash')->willReturn('a-context-hash');
        $cacheHashEvent->method('shouldResponseBeCached')->willReturn(true);

        $cacheHeadersService = static::createStub(CacheHeadersService::class);
        $cacheHeadersService->method('applyCacheHash')->willReturn($cacheHashEvent);

        $cacheSubscriber = new CacheResponseSubscriber(
            true,
            new MaintenanceModeResolver($eventDispatcher),
            $cacheHeadersService,
            CachePolicyProviderFactory::create(
                [
                    'cacheable' => ['headers' => ['cache_control' => ['public' => true, 's_maxage' => 100]]],
                    'uncacheable' => ['headers' => ['cache_control' => ['private' => true, 'no_store' => true]]],
                ],
                [],
                ['channel_api' => ['cacheable' => 'cacheable', 'uncacheable' => 'uncacheable']],
            ),
        );

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
            PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => $fromSession,
        ]);
        // matching the expected hash is what makes the route cacheable at all: on a mismatch
        // CacheResponseSubscriber bypasses the cache by itself
        $request->headers->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'a-context-hash');
        $event = $this->responseEvent($request, new Response());

        // kernel.response order: CacheResponseSubscriber at -1500, ours at -1600
        $cacheSubscriber->setResponseCache($event);
        $this->subscriber([$request])->protectSessionResolvedResponse($event);

        static::assertSame($expected, $event->getResponse()->headers->get('cache-control'));
    }

    /**
     * @return iterable<string, array{0: bool, 1: string}>
     */
    public static function sessionInvolvementProvider(): iterable
    {
        // the control: the route really is stored by a shared cache when no session is involved
        yield 'token based request keeps the cacheable policy' => [false, 'public, s-maxage=100'];
        yield 'session resolved request is never stored' => [true, 'max-age=0, must-revalidate, no-cache, no-store, private'];
    }

    /**
     * @param list<Request> $requests
     * @param array<string, mixed> $config
     */
    private function subscriber(array $requests, array $config = [], bool $enabled = true): SessionContextTokenSubscriber
    {
        return new SessionContextTokenSubscriber(
            new SessionContextTokenAccessor(['name' => 'session-'], $enabled, new StaticSystemConfigService($config), new RouteScopeRegistry([new ChannelApiRouteScope(), new ApiRouteScope()])),
            new RequestStack($requests),
            new RouteScopeRegistry([new ChannelApiRouteScope(), new ApiRouteScope()])
        );
    }

    private function ownerRequest(?string $channelId = null): Request
    {
        $attributes = [ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true];

        if ($channelId !== null) {
            $attributes[PlatformRequest::ATTRIBUTE_CHANNEL_ID] = $channelId;
        }

        return new Request(attributes: $attributes);
    }

    private function sessionWithId(string $id): Session
    {
        $storage = new MockArraySessionStorage();
        $storage->setId($id);

        return new Session($storage);
    }

    private function requestEvent(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(static::createStub(HttpKernelInterface::class), $request, $type);
    }

    private function responseEvent(Request $request, Response $response): ResponseEvent
    {
        return new ResponseEvent(static::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}

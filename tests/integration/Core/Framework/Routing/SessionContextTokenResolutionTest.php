<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Routing;

use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\ChannelRequestContextResolver;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\Framework\Routing\SessionContextTokenSubscriber;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Channel API requests resolve their context token from the frontend session when the caller opts
 * in via `ct-context-source: session` and sends the session cookie but no `ct-context-token`
 * header, and token rotations are written back into that session.
 *
 * @internal
 *
 * @see \Contena\Core\Framework\Routing\SessionContextTokenAccessor
 */
class SessionContextTokenResolutionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const SESSION_ID = 'a-resumable-frontend-session-id';

    private const SUFFIXED_KEY = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . TestDefaults::CHANNEL;

    private ChannelRequestContextResolver $resolver;

    private SessionContextTokenSubscriber $subscriber;

    private string $sessionName;

    protected function setUp(): void
    {
        $this->resolver = static::getContainer()->get(ChannelRequestContextResolver::class);
        $this->subscriber = static::getContainer()->get(SessionContextTokenSubscriber::class);

        /** @var array<string, mixed> $sessionOptions */
        $sessionOptions = static::getContainer()->getParameter('session.storage.options');
        $this->sessionName = (string) ($sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME);
    }

    public function testResolvesTokenFromFrontendSession(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertSame($sessionToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($sessionToken, $this->resolvedContext($request)->getToken());
        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
        static::assertTrue($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testUnderMemberBindingTheChannelKeyIsAuthoritative(): void
    {
        $this->enableMemberBinding();
        $suffixedToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $this->attachSession($request, [
            self::SUFFIXED_KEY => $suffixedToken,
            PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32),
        ]);

        $this->resolve($request);

        static::assertSame($suffixedToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testWithoutMemberBindingAStaleChannelKeyIsIgnored(): void
    {
        $plainToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $this->attachSession($request, [
            self::SUFFIXED_KEY => Random::getAlphanumericString(32),
            PlatformRequest::HEADER_CONTEXT_TOKEN => $plainToken,
        ]);

        $this->resolve($request);

        static::assertSame($plainToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUnderMemberBindingAPlainOnlySessionHoldsNoTokenForTheChannel(): void
    {
        $this->enableMemberBinding();

        $request = $this->createChannelApiRequest();
        // a session created before the binding flag was switched on
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a frontend session holding a context token for this channel'
        ));

        $this->resolve($request);
    }

    public function testWithoutSessionCookieTheDeclaredSessionSourceFails(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);
        // a session attached to the request object but not backed by the cookie must not be borrowed
        $request->cookies->remove($this->sessionName);

        $this->expectExceptionObject(
            RoutingException::sessionContextNotResolvable('the request carries no frontend session cookie')
        );

        $this->resolve($request);
    }

    public function testDeclaringTheSessionSourceAlongsideATokenHeaderFails(): void
    {
        $request = $this->createChannelApiRequest(Random::getAlphanumericString(32));
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the request also carries a ct-context-token header; declare either the session or an explicit token as context source, not both'
        ));

        $this->resolve($request);
    }

    public function testFrontendScopedRequestsAreNotSubjectToTheSessionSourceRules(): void
    {
        $headerToken = Random::getAlphanumericString(32);

        // frontend requests always carry a token header, only Channel API requests are governed
        $request = $this->createChannelApiRequest($headerToken);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['frontend']);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        static::assertSame($headerToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    /**
     * @return list<array{0: string, 1: bool}>
     */
    public static function fetchSiteProvider(): array
    {
        return [
            ['same-origin', true],
            ['same-site', true],
            ['cross-site', false],
            ['none', false],
        ];
    }

    #[DataProvider('fetchSiteProvider')]
    public function testFetchMetadataGatesTheSessionFallback(string $fetchSite, bool $shouldResolve): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $request->headers->set('Sec-Fetch-Site', $fetchSite);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        if (!$shouldResolve) {
            $this->expectExceptionObject(
                RoutingException::sessionContextNotResolvable('the request is not a same-origin or same-site fetch')
            );
        }

        $this->resolve($request);

        static::assertSame($sessionToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testALoginIsWrittenBackToTheSession(): void
    {
        $this->enableMemberBinding();
        $sessionToken = Random::getAlphanumericString(32);
        $loggedInToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $session = $this->attachSession($request, [
            self::SUFFIXED_KEY => $sessionToken,
            PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken,
        ]);

        $this->resolve($request);
        $this->login($request, $loggedInToken);

        static::assertSame($loggedInToken, $session->get(self::SUFFIXED_KEY), 'the channel key must follow the rotation');
        static::assertSame($loggedInToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN), 'the plain key is always kept in sync');
        static::assertSame($loggedInToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $response = $this->respond($request);

        static::assertTrue($response->headers->hasCacheControlDirective('private'));
        static::assertTrue($response->headers->hasCacheControlDirective('no-store'));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN), 'a session sourced response omits the token header');
    }

    public function testARotationMigratesTheSessionId(): void
    {
        $request = $this->createChannelApiRequest();
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        $initialId = $session->getId();
        static::assertSame(self::SESSION_ID, $initialId);

        $this->login($request, Random::getAlphanumericString(32));

        $migratedId = $session->getId();
        static::assertNotSame(
            self::SESSION_ID,
            $migratedId,
            'a token rotation is a privilege boundary: a pre-planted session ID must not survive it'
        );
        static::assertSame($migratedId, $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));
    }

    public function testALogoutDestroysTheSessionAndContinuesOnAFreshToken(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);
        $this->logout($request);

        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertSame(32, \strlen($token));
        static::assertNotSame($sessionToken, $token);
        static::assertNotSame(self::SESSION_ID, $session->getId());
    }

    /**
     * @return iterable<string, array{0: string|null}>
     */
    public static function missingOptInProvider(): iterable
    {
        yield 'no ct-context-source header at all' => [null];
        yield 'an unrecognized ct-context-source value' => ['token'];
    }

    #[DataProvider('missingOptInProvider')]
    public function testWithoutTheOptInHeaderATokenLessRequestKeepsItsClassicSemantics(?string $contextSource): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest(sessionOptIn: false);
        if ($contextSource !== null) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, $contextSource);
        }
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertNotSame(
            $sessionToken,
            $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'without the explicit opt-in a token-less request must get a fresh throwaway context, never the shoppers session'
        );
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
        static::assertFalse($request->attributes->getBoolean(PlatformRequest::ATTRIBUTE_NO_STORE));
    }

    public function testACookieThatDoesNotResumeASessionFails(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        // strict mode: an unknown cookie ends up on a session with a fresh ID
        $this->attachSession(
            $request,
            [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken],
            cookieValue: 'a-stale-or-forged-session-id'
        );

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a frontend session holding a context token for this channel'
        ));

        $this->resolve($request);
    }

    public function testASessionWithoutAContextTokenFails(): void
    {
        $request = $this->createChannelApiRequest();
        // resumable, but not started by the frontend
        $this->attachSession($request, []);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a frontend session holding a context token for this channel'
        ));

        $this->resolve($request);
    }

    public function testSharedCacheableRoutesResolveFromTheSessionButAreNeverStored(): void
    {
        $sessionToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $request->attributes->set('_route', 'channel-api.blog.search');
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);

        static::assertSame($sessionToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($sessionToken, $this->resolvedContext($request)->getToken());
        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));

        // the full kernel.response chain, so CacheResponseSubscriber runs before the no-store enforcement
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $sessionToken);
        static::getContainer()->get('event_dispatcher')->dispatch(
            new ResponseEvent(
                static::getContainer()->get('kernel'),
                $request,
                HttpKernelInterface::MAIN_REQUEST,
                $response
            ),
            KernelEvents::RESPONSE
        );

        static::assertTrue($response->headers->hasCacheControlDirective('no-store'), (string) $response->headers->get('cache-control'));
        static::assertTrue($response->headers->hasCacheControlDirective('private'), (string) $response->headers->get('cache-control'));
        static::assertFalse($response->headers->hasCacheControlDirective('public'), (string) $response->headers->get('cache-control'));
        static::assertFalse($response->headers->hasCacheControlDirective('s-maxage'), (string) $response->headers->get('cache-control'));
        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testATokenHeaderWithoutTheSessionSourceLeavesTheSessionAlone(): void
    {
        $sessionToken = Random::getAlphanumericString(32);
        $foreignToken = Random::getAlphanumericString(32);

        $request = $this->createChannelApiRequest($foreignToken, sessionOptIn: false);
        $session = $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => $sessionToken]);

        $this->resolve($request);
        $this->login($request, Random::getAlphanumericString(32));

        static::assertSame($foreignToken, $this->resolvedContext($request)->getToken());
        static::assertSame(
            $sessionToken,
            $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
            'a caller that brought its own token must not repoint the shoppers session'
        );
        static::assertSame(self::SESSION_ID, $session->getId());
    }

    public function testAResponseWithoutSessionInvolvementKeepsItsCacheHeaders(): void
    {
        $request = $this->createChannelApiRequest(Random::getAlphanumericString(32), sessionOptIn: false);
        $this->attachSession($request, [PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32)]);

        $this->resolve($request);

        $response = $this->respond($request);

        static::assertFalse($response->headers->hasCacheControlDirective('no-store'));
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN), 'a client that manages its own token keeps getting it back');
    }

    private function createChannelApiRequest(?string $contextToken = null, bool $sessionOptIn = true): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ChannelApiRouteScope::ID]);

        if ($sessionOptIn) {
            $request->headers->set(
                PlatformRequest::HEADER_CONTEXT_SOURCE,
                SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION
            );
        }

        if ($contextToken !== null) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        }

        return $request;
    }

    /**
     * @param array<string, string> $data
     */
    private function attachSession(Request $request, array $data, string $cookieValue = self::SESSION_ID): SessionInterface
    {
        $storage = new MockArraySessionStorage();
        // the accessor only trusts a session whose ID matches the cookie
        $storage->setId(self::SESSION_ID);
        $session = new Session($storage);

        foreach ($data as $key => $value) {
            $session->set($key, $value);
        }

        $request->setSession($session);
        $request->cookies->set($this->sessionName, $cookieValue);

        return $session;
    }

    private function enableMemberBinding(): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.systemWideLoginRegistration.isMemberBoundToChannel', true);
    }

    private function resolve(Request $request): void
    {
        $this->onStack($request, fn () => $this->resolver->resolve($request));
    }

    private function login(Request $request, string $token): void
    {
        $this->onStack($request, fn () => $this->subscriber->onMemberLogin(
            new MemberLoginEvent($this->resolvedContext($request), new MemberEntity(), $token)
        ));
    }

    private function logout(Request $request): void
    {
        $this->onStack($request, fn () => $this->subscriber->onMemberLogout(
            new MemberLogoutEvent($this->resolvedContext($request), new MemberEntity())
        ));
    }

    /**
     * A response as it reaches the last listener: with the context token echoed onto it.
     */
    private function respond(Request $request): Response
    {
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, (string) $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->subscriber->protectSessionResolvedResponse(new ResponseEvent(
            static::getContainer()->get('kernel'),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response
        ));

        return $response;
    }

    private function onStack(Request $request, callable $action): void
    {
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        try {
            $action();
        } finally {
            $requestStack->pop();
        }
    }

    private function resolvedContext(Request $request): ChannelContext
    {
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);

        static::assertInstanceOf(ChannelContext::class, $context);

        return $context;
    }
}

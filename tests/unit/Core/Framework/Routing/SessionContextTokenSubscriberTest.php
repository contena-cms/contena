<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Event\UserLogoutEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Routing\SessionContextTokenAccessor;
use Contena\Core\Framework\Routing\SessionContextTokenSubscriber;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
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
    private const BINDING_ENABLED = ['core.systemWideLoginRegistration.isUserBoundToChannel' => true];

    public function testHasEvents(): void
    {
        static::assertSame([
            KernelEvents::REQUEST => [['startSession', 40]],
            KernelEvents::CONTROLLER => [['resolveFromSession', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_CONTEXT_RESOLVE_PRE]],
            KernelEvents::RESPONSE => [['stripContextToken', -1600]],
            UserLoginEvent::class => 'onUserLogin',
            UserLogoutEvent::class => 'onUserLogout',
            ChannelContextResolvedEvent::class => 'onContextResolved',
        ], SessionContextTokenSubscriber::getSubscribedEvents());
    }

    public function testStartSessionStampsTheSessionIdAndMintsAToken(): void
    {
        $request = $this->ownerRequest('sales-channel-a');
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
        $mainRequest = $this->ownerRequest('sales-channel-a');
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
        $request = $this->ownerRequest('sales-channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request])->startSession($this->requestEvent($request));

        static::assertTrue($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a'));
    }

    public function testWithBindingTheTokenLivesInTheChannelKey(): void
    {
        $request = $this->ownerRequest('sales-channel-a');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->subscriber([$request], self::BINDING_ENABLED)->startSession($this->requestEvent($request));

        $session = $request->getSession();
        $channelToken = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a');
        static::assertNotNull($channelToken);
        static::assertSame($channelToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($channelToken, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN), 'the plain key mirrors the channel being browsed');
    }

    public function testWithBindingTokensSurviveSwitchingChannels(): void
    {
        $session = new Session(new MockArraySessionStorage());

        $requestA = $this->ownerRequest('sales-channel-a');
        $requestA->setSession($session);
        $this->subscriber([$requestA], self::BINDING_ENABLED)->startSession($this->requestEvent($requestA));
        $tokenA = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a');

        $requestB = $this->ownerRequest('sales-channel-b');
        $requestB->setSession($session);
        $this->subscriber([$requestB], self::BINDING_ENABLED)->startSession($this->requestEvent($requestB));
        $tokenB = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-b');

        static::assertNotNull($tokenA);
        static::assertNotNull($tokenB);
        static::assertNotSame($tokenA, $tokenB);

        $requestA2 = $this->ownerRequest('sales-channel-a');
        $requestA2->setSession($session);
        $this->subscriber([$requestA2], self::BINDING_ENABLED)->startSession($this->requestEvent($requestA2));

        static::assertSame($tokenA, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-a'), 'returning to a channel resumes its token');
        static::assertSame($tokenA, $requestA2->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($tokenB, $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-sales-channel-b'));
    }

    public function testLoginRotatesTheTokenAndTheSessionId(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = $this->ownerRequest($context->getChannelId());
        $session = $this->sessionWithId('before-login');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'anonymous');
        $request->setSession($session);

        $this->subscriber([$request])->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'logged-in'));

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
            ->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'logged-in'));

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

        $this->subscriber([$request])->onUserLogout(new UserLogoutEvent($context, new UserEntity()));

        static::assertSame('the-routes-own-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('the-routes-own-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('logged-in-session', $session->getId());
    }

    public function testLogoutContinuesOnASeparateFreshTokenBeforeV680(): void
    {
        $context = Generator::generateChannelContext(token: 'the-routes-own-token');
        $request = $this->ownerRequest($context->getChannelId());
        $session = $this->sessionWithId('logged-in-session');
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'logged-in');
        $request->setSession($session);

        Feature::fake([], function () use ($request, $context): void {
            $this->subscriber([$request])->onUserLogout(new UserLogoutEvent($context, new UserEntity()));
        });

        $token = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($token);
        static::assertSame(32, \strlen($token));
        static::assertNotSame('logged-in', $token);
        static::assertNotSame('the-routes-own-token', $token);
        static::assertSame($token, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
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
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
            ]);
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
            $request->setSession($session);
            $accessor = new SessionContextTokenAccessor(['name' => 'session-'], new StaticSystemConfigService());
            static::assertSame('expired', $accessor->read($request, $context->getChannelId()));

            $subscriber = $this->subscriber([$request]);
            $subscriber->onContextResolved(new ChannelContextResolvedEvent($context, 'expired'));
            $replacementId = $session->getId();
            static::assertNotSame($originalId, $replacementId);

            $subscriber->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'logged-in'));

            static::assertTrue($session->isStarted(), 'Symfony must still emit the migrated session cookie');
            static::assertNotSame($replacementId, $session->getId());
            static::assertSame('logged-in', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertSame('logged-in', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertSame($session->getId(), $session->get(SessionContextTokenAccessor::SESSION_ID_KEY));

            $response = new Response();
            (new SessionListener())->onKernelResponse($this->responseEvent($request, $response));
            $cookies = $response->headers->getCookies();
            static::assertCount(1, $cookies);
            static::assertSame('session-', $cookies[0]->getName());
            static::assertSame($session->getId(), $cookies[0]->getValue());
            static::assertFalse($session->isStarted());

            $nextRequest = new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]],
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

        $this->subscriber([])->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'logged-in'));

        $this->expectNotToPerformAssertions();
    }

    public function testRotationOnAPlainStoreApiRequestLeavesTheSessionAlone(): void
    {
        $context = Generator::generateChannelContext(token: 'logged-in');
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        $this->subscriber([$request])->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'logged-in'));

        static::assertSame(0, $factoryCalls, 'a Store API request that did not declare the session as its source never touches it');
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

        $this->subscriber([$request])->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'logged-in'));

        static::assertSame(0, $factoryCalls);
    }

    public function testASessionSourcedRequestResolvesTheSessionsToken(): void
    {
        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);

        $this->subscriber([$request])->resolveFromSession($this->controllerEvent($request));

        static::assertSame('the-sessions-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertTrue($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    public function testAStoreApiRequestWithoutTheSourceHeaderIsLeftAlone(): void
    {
        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);
        $request->headers->remove(PlatformRequest::HEADER_CONTEXT_SOURCE);

        $this->subscriber([$request])->resolveFromSession($this->controllerEvent($request));

        static::assertFalse($request->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertFalse($request->attributes->getBoolean(SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION));
    }

    public function testASessionSourcedRequestMustNotCarryATokenHeader(): void
    {
        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'an-explicit-token');

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the request also carries a sw-context-token header; declare either the session or an explicit token as context source, not both'
        ));

        $this->subscriber([$request])->resolveFromSession($this->controllerEvent($request));
    }

    public function testASessionSourcedRequestFailsWithoutASessionCookie(): void
    {
        $request = $this->sessionSourcedRequest([PlatformRequest::HEADER_CONTEXT_TOKEN => 'the-sessions-token']);
        $request->cookies->remove('session-');

        $this->expectExceptionObject(
            RoutingException::sessionContextNotResolvable('the request carries no frontend session cookie')
        );

        $this->subscriber([$request])->resolveFromSession($this->controllerEvent($request));
    }

    public function testASessionSourcedRequestFailsWhenTheSessionHoldsNoToken(): void
    {
        $request = $this->sessionSourcedRequest([]);

        $this->expectExceptionObject(RoutingException::sessionContextNotResolvable(
            'the session cookie does not resume a frontend session holding a context token for this sales channel'
        ));

        $this->subscriber([$request])->resolveFromSession($this->controllerEvent($request));
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

        $this->subscriber([$request])->onUserLogin(new UserLoginEvent($context, new UserEntity(), 'rotated'));

        static::assertSame('untouched', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('frontend-session', $session->getId());
    }

    public function testSessionResolvedStoreApiResponsesOmitTheTokenHeader(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        // what ResponseHeaderListener's echo and ContextTokenResponse put there
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'the-shoppers-token');

        $this->subscriber([$request])->stripContextToken($this->responseEvent($request, $response));

        static::assertFalse($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testOtherStoreApiResponsesKeepTheirToken(): void
    {
        $request = new Request(attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]]);
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'a-client-managed-token');

        $this->subscriber([$request])->stripContextToken($this->responseEvent($request, $response));

        static::assertSame('a-client-managed-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testResponsesOutsideTheStoreApiAreNotTouched(): void
    {
        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID],
            SessionContextTokenAccessor::ATTRIBUTE_TOKEN_FROM_SESSION => true,
        ]);
        $response = new Response();
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'a-client-managed-token');

        $this->subscriber([$request])->stripContextToken($this->responseEvent($request, $response));

        static::assertSame('a-client-managed-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    /**
     * @param list<Request> $requests
     * @param array<string, mixed> $config
     */
    private function subscriber(array $requests, array $config = []): SessionContextTokenSubscriber
    {
        return new SessionContextTokenSubscriber(
            new SessionContextTokenAccessor(['name' => 'session-'], new StaticSystemConfigService($config)),
            new RequestStack($requests),
            new RouteScopeRegistry([new StoreApiRouteScope(), new ApiRouteScope()])
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

    /**
     * @param array<string, string> $sessionData
     */
    private function sessionSourcedRequest(array $sessionData): Request
    {
        $session = $this->sessionWithId('a-resumable-session');
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_CHANNEL_ID => 'a-sales-channel',
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        ], cookies: ['session-' => 'a-resumable-session']);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_SOURCE, SessionContextTokenAccessor::CONTEXT_SOURCE_SESSION);
        $request->setSession($session);

        return $request;
    }

    private function controllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
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

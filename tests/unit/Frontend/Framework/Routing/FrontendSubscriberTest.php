<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\ChannelRequest;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Routing\KernelListenerPriorities;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Member\Event\MemberLoginEvent;
use Contena\Core\System\Member\Event\MemberLogoutEvent;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Frontend\Event\MaintenanceRedirectEvent;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\FrontendSubscriber;
use Contena\Frontend\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(FrontendSubscriber::class)]
class FrontendSubscriberTest extends TestCase
{
    private const TEST_CONTEXT_TOKEN = 'test-context-token';

    public function testHasEvents(): void
    {
        $expected = [
            KernelEvents::REQUEST => [
                ['startSession', 40],
                ['maintenanceResolver'],
            ],
            KernelEvents::EXCEPTION => [
                ['memberNotLoggedInHandler'],
                ['maintenanceResolver'],
            ],
            KernelEvents::CONTROLLER => [
                ['preventPageLoadingFromXmlHttpRequest', KernelListenerPriorities::KERNEL_CONTROLLER_EVENT_SCOPE_VALIDATE],
            ],
            MemberLoginEvent::class => [
                'updateSessionAfterLogin',
            ],
            MemberLogoutEvent::class => [
                'updateSessionAfterLogout',
            ],
            ChannelContextResolvedEvent::class => [
                ['replaceContextToken'],
            ],
        ];

        static::assertSame($expected, FrontendSubscriber::getSubscribedEvents());
    }

    public function testMaintenanceRedirect(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver
            ->method('shouldRedirect')
            ->willReturn(true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/maintenance');

        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST
        );

        $eventDispatcher = new EventDispatcher();
        $eventIsThrown = false;
        $eventDispatcher->addListener(
            MaintenanceRedirectEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        new FrontendSubscriber(
            new RequestStack(),
            $router,
            $maintenanceModeResolver,
            new StaticSystemConfigService(),
            $eventDispatcher,
        )->maintenanceResolver($event);

        $response = $event->getResponse();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/maintenance', $response->getTargetUrl());
        static::assertTrue($eventIsThrown);
    }

    public function testMaintenanceParametersRedirect(): void
    {
        $maintenanceModeResolver = static::createStub(MaintenanceModeResolver::class);
        $maintenanceModeResolver
            ->method('shouldRedirect')
            ->willReturn(true);

        $router = static::createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/maintenance?foo=bar');

        $request = new Request(
            query: [
                'bar' => 'foo',
            ],
            attributes: [
                '_route' => 'product_page',
                '_route_params' => [
                    'foo' => 'bar',
                    'productId' => 123,
                    PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS[0] => true,
                    PlatformRequest::ATTRIBUTE_INTERNAL_ROUTE_PARAMS[1] => true,
                ],
            ],
        );

        $event = new RequestEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $eventDispatcher = new EventDispatcher();
        $eventIsThrown = false;
        $eventDispatcher->addListener(
            MaintenanceRedirectEvent::class,
            static function (MaintenanceRedirectEvent $event) use (&$eventIsThrown): void {
                $parameters = $event->getParameters();
                static::assertEquals('product_page', $parameters['redirectTo']);
                static::assertEquals('{"bar":"foo","foo":"bar","productId":123}', $parameters['redirectParameters']);

                $eventIsThrown = true;
            }
        );

        new FrontendSubscriber(
            new RequestStack(),
            $router,
            $maintenanceModeResolver,
            new StaticSystemConfigService(),
            $eventDispatcher,
        )->maintenanceResolver($event);

        static::assertTrue($eventIsThrown);
    }

    #[DataProvider('memberNotLoggedInHandlerProvider')]
    public function testMemberNotLoggedInHandler(\Throwable $exception, bool $isXmlHttpRequest, bool $expectRedirect): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects($expectRedirect ? $this->once() : $this->never())
            ->method('generate')
            ->with('frontend.account.login.page')
            ->willReturn('/login');

        $server = $isXmlHttpRequest ? ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] : [];

        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(
                attributes: [ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true],
                server: $server,
            ),
            HttpKernelInterface::MAIN_REQUEST,
            $exception
        );

        new FrontendSubscriber(
            static::createStub(RequestStack::class),
            $router,
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->memberNotLoggedInHandler($event);

        if ($expectRedirect) {
            static::assertInstanceOf(RedirectResponse::class, $event->getResponse());

            return;
        }

        static::assertFalse($event->hasResponse());
    }

    public function testRedirectMemberNonFrontendRequest(): void
    {
        $event = new ExceptionEvent(
            static::createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('test')
        );

        new FrontendSubscriber(
            static::createStub(RequestStack::class),
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->memberNotLoggedInHandler($event);

        static::assertFalse($event->hasResponse());
    }

    public static function memberNotLoggedInHandlerProvider(): \Generator
    {
        yield 'routing exception redirects regular request' => [
            'exception' => RoutingException::channelMemberNotLoggedIn(),
            'isXmlHttpRequest' => false,
            'expectRedirect' => true,
        ];

        yield 'routing exception does not redirect XHR request' => [
            'exception' => RoutingException::channelMemberNotLoggedIn(),
            'isXmlHttpRequest' => true,
            'expectRedirect' => false,
        ];

        yield 'unrelated exception does not redirect' => [
            'exception' => new \RuntimeException('test'),
            'isXmlHttpRequest' => false,
            'expectRedirect' => false,
        ];
    }

    #[DataProvider('dataProviderXMLHttpRequest')]
    public function testNonXmlHttpRequestPassesThrough(Request $request, bool $expected): void
    {
        $event = new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static function (): void {},
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        if ($expected) {
            $route = $request->attributes->get('_route');
            $url = $request->getUri();
            $referer = $request->headers->get('referer');

            $this->expectExceptionObject(RoutingException::accessDeniedForXmlHttpRequest($route, $url, $referer));
        } else {
            static::assertTrue($event->isMainRequest());
        }

        new FrontendSubscriber(
            new RequestStack(),
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->preventPageLoadingFromXmlHttpRequest($event);
    }

    public static function dataProviderXMLHttpRequest(): \Generator
    {
        yield 'not an XMLHttpRequest' => [
            'request' => new Request(),
            'expected' => false,
        ];

        yield 'XMLHttpRequest, but not a frontend request' => [
            'request' => new Request(server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']),
            'expected' => false,
        ];

        yield 'XMLHttpRequest, but a frontend request and not allowed' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]],
                server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            ),
            'expected' => true,
        ];

        yield 'XMLHttpRequest, but a frontend request and allowed' => [
            'request' => new Request(
                attributes: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID], 'XmlHttpRequest' => true],
                server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
            ),
            'expected' => false,
        ];
    }

    public function testStartSession(): void
    {
        $request = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => Generator::generateChannelContext(),
            ],
            server: ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();

        $requestStack->push($request);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->startSession();

        static::assertTrue($request->getSession()->has('sessionId'));
    }

    public function testDoesNotStartSessionWithoutFrontendChannelMarker(): void
    {
        $request = new Request();
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        new FrontendSubscriber(
            new RequestStack([$request]),
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->startSession();

        static::assertSame(0, $factoryCalls);
    }

    public function testSubRequestShouldGetSameContextTokenAsMainRequest(): void
    {
        $mainRequest = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => 'test-channel-id',
            ]
        );

        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, self::TEST_CONTEXT_TOKEN);
        $mainRequest->setSession($session);

        $subRequest = new Request();
        $requestStack = new RequestStack([$mainRequest, $subRequest]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->startSession();

        $subRequestContextToken = $subRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertSame(self::TEST_CONTEXT_TOKEN, $subRequestContextToken);
        static::assertSame($mainRequest->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN), $subRequestContextToken);
    }

    public function testUpdateSessionWithoutRequest(): void
    {
        $requestStack = new RequestStack();

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertNull($requestStack->getCurrentRequest());
    }

    public function testUpdateSessionIsNoChannelRequest(): void
    {
        $request = new Request();

        new FrontendSubscriber(
            new RequestStack([$request]),
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUpdateSessionWithoutSession(): void
    {
        $request = new Request(attributes: [
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID],
        ]);
        $requestStack = new RequestStack([$request]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUpdateSession(): void
    {
        $request = new Request(attributes: [
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID],
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertSame(self::TEST_CONTEXT_TOKEN, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(self::TEST_CONTEXT_TOKEN, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testDoesNotUpdateSessionForChannelApiRequest(): void
    {
        $request = new Request(attributes: [
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID],
        ]);
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use (&$factoryCalls): Session {
            ++$factoryCalls;

            return new Session(new MockArraySessionStorage());
        });

        new FrontendSubscriber(
            new RequestStack([$request]),
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            new StaticSystemConfigService(),
            new EventDispatcher(),
        )->updateSession(self::TEST_CONTEXT_TOKEN);

        static::assertSame(0, $factoryCalls);
    }

    public function testStartSessionWithBindingDisabledUsesDefaultTokenKey(): void
    {
        $channelId = 'test-channel-id';
        $request = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isMemberBoundToChannel' => false,
        ]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->startSession();

        // Should use default token key
        static::assertTrue($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        // Should NOT use channel-specific key
        static::assertFalse($request->getSession()->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId));
    }

    public function testStartSessionWithBindingEnabledUsesChannelSpecificTokenKey(): void
    {
        $channelId = 'test-channel-id';
        $request = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isMemberBoundToChannel' => true,
        ]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->startSession();

        // Should use channel-specific token key
        $channelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;
        static::assertTrue($request->getSession()->has($channelTokenKey));

        // Token should be set in request header
        static::assertNotNull($request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame(
            $request->getSession()->get($channelTokenKey),
            $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
        );
    }

    public function testStartSessionWithBindingEnabledPreservesTokensAcrossChannels(): void
    {
        $channelIdA = 'channel-a';
        $channelIdB = 'channel-b';

        $session = new Session(new MockArraySessionStorage());

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isMemberBoundToChannel' => true,
        ]);

        // Visit Channel A
        $requestA = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelIdA,
            ]
        );
        $requestA->setSession($session);
        $requestStackA = new RequestStack([$requestA]);

        new FrontendSubscriber(
            $requestStackA,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->startSession();

        $tokenA = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelIdA);
        static::assertNotNull($tokenA);

        // Visit Channel B
        $requestB = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelIdB,
            ]
        );
        $requestB->setSession($session);
        $requestStackB = new RequestStack([$requestB]);

        new FrontendSubscriber(
            $requestStackB,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->startSession();

        $tokenB = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelIdB);
        static::assertNotNull($tokenB);
        static::assertNotSame($tokenA, $tokenB);

        // Return to Channel A - token should be preserved
        $requestA2 = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelIdA,
            ]
        );
        $requestA2->setSession($session);
        $requestStackA2 = new RequestStack([$requestA2]);

        new FrontendSubscriber(
            $requestStackA2,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->startSession();

        $tokenA2 = $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelIdA);
        static::assertSame($tokenA, $tokenA2, 'Token for Channel A should be preserved');

        // Both tokens should still exist
        static::assertTrue($session->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelIdA));
        static::assertTrue($session->has(PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelIdB));
    }

    public function testUpdateSessionWithBindingEnabledStoresTokenInChannelKey(): void
    {
        $channelId = 'test-channel-id';
        $newToken = 'new-context-token';

        $request = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID],
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isMemberBoundToChannel' => true,
        ]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->updateSession($newToken);

        // Should store in both channel-specific and default keys
        $channelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;
        static::assertSame($newToken, $request->getSession()->get($channelTokenKey));
        static::assertSame($newToken, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame($newToken, $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testUpdateSessionWithBindingDisabledStoresTokenInDefaultKeyOnly(): void
    {
        $channelId = 'test-channel-id';
        $newToken = 'new-context-token';

        $request = new Request(
            attributes: [
                ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
                PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID],
                PlatformRequest::ATTRIBUTE_CHANNEL_ID => $channelId,
            ]
        );
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack([$request]);

        $configService = new StaticSystemConfigService([
            'core.systemWideLoginRegistration.isMemberBoundToChannel' => false,
        ]);

        new FrontendSubscriber(
            $requestStack,
            static::createStub(RouterInterface::class),
            static::createStub(MaintenanceModeResolver::class),
            $configService,
            new EventDispatcher(),
        )->updateSession($newToken);

        // Should only store in default key
        static::assertSame($newToken, $request->getSession()->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        // Should NOT store in channel-specific key
        $channelTokenKey = PlatformRequest::HEADER_CONTEXT_TOKEN . '-' . $channelId;
        static::assertFalse($request->getSession()->has($channelTokenKey));
    }
}

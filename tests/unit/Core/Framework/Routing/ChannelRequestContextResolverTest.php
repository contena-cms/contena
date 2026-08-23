<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\ChannelRequestContextResolver;
use Contena\Core\Framework\Routing\RequestContextResolverInterface;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * @internal
 */
#[CoversClass(ChannelRequestContextResolver::class)]
class ChannelRequestContextResolverTest extends TestCase
{
    #[TestDox('Channel API context resolution leaves the session untouched')]
    #[DataProvider('sessionStateProvider')]
    public function testResolutionLeavesSessionUntouched(bool $sessionAlreadyInstantiated): void
    {
        $context = static::createStub(ChannelContext::class);
        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (ChannelContextServiceParameters $parameters) use ($context): ChannelContext {
                static::assertNull($parameters->getImitatingUserId());

                return $context;
            });

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ChannelApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'test-token');
        $storage = new MockArraySessionStorage();
        $factoryCalls = 0;
        $request->setSessionFactory(static function () use ($storage, &$factoryCalls): Session {
            ++$factoryCalls;

            return new Session($storage);
        });

        if ($sessionAlreadyInstantiated) {
            $request->getSession();
        }
        $factoryCallsBeforeResolve = $factoryCalls;

        $resolver = new ChannelRequestContextResolver(
            static::createStub(RequestContextResolverInterface::class),
            $contextService,
            new EventDispatcher(),
            new RouteScopeRegistry([new ChannelApiRouteScope()]),
        );

        $resolver->resolve($request);

        static::assertFalse($storage->isStarted(), 'Channel API context resolution must not start the session.');
        static::assertSame($factoryCallsBeforeResolve, $factoryCalls, 'Channel API context resolution must not invoke the lazy session factory.');
    }

    public static function sessionStateProvider(): \Generator
    {
        yield 'request has only the lazy session factory' => [false];
        yield 'session was instantiated but not started' => [true];
    }

    public function testEmptyLanguageHeaderIsIgnored(): void
    {
        $context = static::createStub(ChannelContext::class);
        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (ChannelContextServiceParameters $parameters) use ($context): ChannelContext {
                static::assertSame(TestDefaults::CHANNEL, $parameters->getChannelId());
                static::assertSame('test-token', $parameters->getToken());
                static::assertNull($parameters->getLanguageId());

                return $context;
            });

        $decorated = $this->createMock(RequestContextResolverInterface::class);
        $decorated
            ->expects($this->never())
            ->method('resolve');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_ID, TestDefaults::CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [ChannelApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'test-token');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, '');

        $resolver = new ChannelRequestContextResolver(
            $decorated,
            $contextService,
            new EventDispatcher(),
            new RouteScopeRegistry([new ChannelApiRouteScope()]),
        );

        $resolver->resolve($request);
    }
}

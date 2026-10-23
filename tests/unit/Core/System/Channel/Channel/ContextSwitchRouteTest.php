<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\Extension\ContextSwitchRouteExtension;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ContextSwitchRoute::class)]
class ContextSwitchRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(ContextSwitchRoute::class));

        (new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            static::createStub(ChannelContextPersister::class),
            $this->createEventDispatcher(),
            static::createStub(ChannelContextServiceInterface::class),
            new ExtensionDispatcher(new EventDispatcher())
        ))->getDecorated();
    }

    public function testSwitchContextAllowsEmptyAddressIdsForAnonymousContext(): void
    {
        $token = 'test-token';
        $channelId = Uuid::randomHex();
        $frameworkContext = Context::createDefaultContext();
        $channelContext = $this->createChannelContext($token, $channelId, $frameworkContext);

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->exactly(2))
            ->method('validate');

        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister
            ->expects($this->once())
            ->method('save')
            ->with(
                $token,
                [
                    ChannelContextService::BILLING_ADDRESS_ID => '',
                    ChannelContextService::SHIPPING_ADDRESS_ID => '',
                ],
                $channelId,
                null
            );

        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new ChannelContextServiceParameters($channelId, $token)))
            ->willReturn($channelContext);

        $route = new ContextSwitchRoute(
            $validator,
            $contextPersister,
            $this->createEventDispatcher(),
            $contextService,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $response = $route->switchContext(
            new RequestDataBag([
                ChannelContextService::BILLING_ADDRESS_ID => '',
                ChannelContextService::SHIPPING_ADDRESS_ID => '',
            ]),
            $channelContext
        );

        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testSwitchContextAllowsAddressIdsForUserContext(): void
    {
        $token = 'test-token';
        $channelId = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();
        $frameworkContext = Context::createDefaultContext();
        $user = new UserEntity();
        $user->setId($userId);

        $channelContext = $this->createChannelContext(
            $token,
            $channelId,
            $frameworkContext,
            $user
        );

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->exactly(2))
            ->method('validate');

        $contextPersister = $this->createMock(ChannelContextPersister::class);
        $contextPersister
            ->expects($this->once())
            ->method('save')
            ->with(
                $token,
                [
                    ChannelContextService::BILLING_ADDRESS_ID => $billingAddressId,
                    ChannelContextService::SHIPPING_ADDRESS_ID => $shippingAddressId,
                ],
                $channelId,
                $userId
            );

        $contextService = $this->createMock(ChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new ChannelContextServiceParameters($channelId, $token)))
            ->willReturn($channelContext);

        $route = new ContextSwitchRoute(
            $validator,
            $contextPersister,
            $this->createEventDispatcher(),
            $contextService,
            new ExtensionDispatcher(new EventDispatcher())
        );

        $response = $route->switchContext(
            new RequestDataBag([
                ChannelContextService::BILLING_ADDRESS_ID => $billingAddressId,
                ChannelContextService::SHIPPING_ADDRESS_ID => $shippingAddressId,
            ]),
            $channelContext
        );

        static::assertSame($token, $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    /**
     * @param array<string, string> $parameters
     */
    #[DataProvider('nonEmptyAddressIdProvider')]
    public function testSwitchContextRejectsNonEmptyAddressIdsForAnonymousContext(array $parameters): void
    {
        $route = new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            static::createStub(ChannelContextPersister::class),
            $this->createEventDispatcher(),
            static::createStub(ChannelContextServiceInterface::class),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->expectExceptionObject(CartException::userNotLoggedIn());

        $route->switchContext(
            new RequestDataBag($parameters),
            $this->createChannelContext('test-token', Uuid::randomHex(), Context::createDefaultContext())
        );
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function nonEmptyAddressIdProvider(): iterable
    {
        yield 'billing address id' => [[ChannelContextService::BILLING_ADDRESS_ID => '0']];
        yield 'shipping address id' => [[ChannelContextService::SHIPPING_ADDRESS_ID => '0']];
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag([ChannelContextService::LANGUAGE_ID => Uuid::randomHex()]);
        $context = Generator::generateChannelContext();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('context-switch-route.switch-context.pre', static function (ContextSwitchRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ContextSwitchRoute(
            static::createStub(DataValidator::class),
            static::createStub(ChannelContextPersister::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ChannelContextServiceInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->switchContext($data, $context));
    }

    private function createChannelContext(
        string $token,
        string $channelId,
        Context $frameworkContext,
        ?UserEntity $user = null
    ): ChannelContext {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext
            ->method('getUser')
            ->willReturn($user);
        $channelContext
            ->method('getUserId')
            ->willReturn($user?->getId());
        $channelContext
            ->method('getToken')
            ->willReturn($token);
        $channelContext
            ->method('getChannelId')
            ->willReturn($channelId);
        $channelContext
            ->method('getContext')
            ->willReturn($frameworkContext);
        $channelContext
            ->method('getPermissions')
            ->willReturn([]);

        return $channelContext;
    }

    private function createEventDispatcher(): EventDispatcherInterface
    {
        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static fn (object $event): object => $event);

        return $eventDispatcher;
    }
}

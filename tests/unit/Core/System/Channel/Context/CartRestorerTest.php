<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Context\CartRestorer;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Event\ChannelContextRestoredEvent;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(CartRestorer::class)]
class CartRestorerTest extends TestCase
{
    private MockObject&ChannelContextFactory $channelContextFactory;

    private ChannelContextPersister&MockObject $persister;

    private CartService&Stub $cartService;

    private CartPersister&Stub $cartPersister;

    private EventDispatcher $eventDispatcher;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->channelContextFactory = $this->createMock(ChannelContextFactory::class);
        $this->persister = $this->createMock(ChannelContextPersister::class);
        $this->cartService = static::createStub(CartService::class);
        $this->cartPersister = static::createStub(CartPersister::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->requestStack = new RequestStack();
    }

    public function testRestoreByTokenWithoutExistingToken(): void
    {
        $token = 'myToken';
        $channelContext = Generator::generateChannelContext();
        $this->persister->expects($this->once())->method('load')->with($token, $channelContext->getChannelId())->willReturn([]);
        $this->persister->expects($this->once())->method('save');

        $userContext = Generator::generateChannelContext(token: $token);
        $this->channelContextFactory->expects($this->once())
            ->method('create')
            ->with($token, $channelContext->getChannelId(), [
                ChannelContextService::CUSTOMER_ID => 'myUser',
                ChannelContextService::LANGUAGE_ID => $channelContext->getLanguageId(),
                ChannelContextService::CURRENCY_ID => $channelContext->getCurrencyId(),
                ChannelContextService::DOMAIN_ID => $channelContext->getDomainId(),
            ])
            ->willReturn($userContext);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculateByToken')
            ->with($token, $userContext);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            ChannelContextRestoredEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->channelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myUser', $channelContext);
        static::assertSame($userContext, $result);
        static::assertSame($token, $result->getToken());
        static::assertFalse($eventIsThrown);
    }

    public function testRestoreByToken(): void
    {
        $token = 'myToken';
        $channelContext = Generator::generateChannelContext();
        $this->persister->expects($this->once())->method('load')->with($token, $channelContext->getChannelId())->willReturn([
            'token' => $token,
            'expired' => false,
        ]);
        $this->persister->expects($this->never())->method('save');

        $this->channelContextFactory->expects($this->once())->method('create')->willReturn(
            Generator::generateChannelContext(token: $token)
        );

        $cartCalculator = static::createStub(CartCalculator::class);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            ChannelContextRestoredEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->channelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myUser', $channelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }

    public function testRestoreWithoutExistingUserContextCreatesUserContext(): void
    {
        $userId = 'myUser';
        $newToken = 'newToken';
        $currentContext = Generator::generateChannelContext();
        $currentContext->addState('foo');

        // No persisted user context exists, e.g. because all user tokens
        // were revoked after a password change.
        $this->persister->expects($this->once())
            ->method('load')
            ->with($currentContext->getToken(), $currentContext->getChannelId(), $userId)
            ->willReturn([
                'token' => $currentContext->getToken(),
                'expired' => false,
            ]);
        $this->persister->expects($this->once())->method('replace')->willReturn($newToken);
        $this->persister->expects($this->once())->method('save');

        $userContext = Generator::generateChannelContext(token: $newToken);
        $this->channelContextFactory->expects($this->once())
            ->method('create')
            ->with($newToken, $currentContext->getChannelId(), [
                ChannelContextService::CUSTOMER_ID => $userId,
                ChannelContextService::LANGUAGE_ID => $currentContext->getLanguageId(),
                ChannelContextService::CURRENCY_ID => $currentContext->getCurrencyId(),
                ChannelContextService::DOMAIN_ID => $currentContext->getDomainId(),
            ])
            ->willReturn($userContext);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculateByToken')
            ->with($newToken, $userContext);

        $cartRestorer = new CartRestorer(
            $this->channelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restore($userId, $currentContext);

        static::assertSame($userContext, $result);
        static::assertTrue($result->hasState('foo'));
    }

    public function testRestoreWithSameUserInContextKeepsContext(): void
    {
        $currentContext = Generator::generateChannelContext();
        $user = $currentContext->getUser();
        static::assertNotNull($user);

        $this->persister->expects($this->once())
            ->method('load')
            ->willReturn([
                'token' => $currentContext->getToken(),
                'expired' => false,
            ]);
        $this->persister->expects($this->once())->method('replace')->willReturn('newToken');
        $this->persister->expects($this->once())->method('save');

        $this->channelContextFactory->expects($this->never())->method('create');
        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->never())->method('calculateByToken');

        $cartRestorer = new CartRestorer(
            $this->channelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restore($user->getId(), $currentContext);

        static::assertSame($currentContext, $result);
        static::assertSame('newToken', $result->getToken());
    }

    public function testRestoreByTokenWithExpiredToken(): void
    {
        $token = 'myToken';
        $channelContext = Generator::generateChannelContext();
        $this->persister->expects($this->once())->method('load')->with($token, $channelContext->getChannelId())->willReturn([
            'token' => $token,
            'expired' => true,
        ]);
        $this->persister->expects($this->once())->method('save');

        // The first call creates the context from the expired payload, the second one creates
        // the user context, as the expired payload does not contain the user anymore.
        $this->channelContextFactory->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            Generator::generateChannelContext(token: $token),
            Generator::generateChannelContext(token: $token)
        );

        $cartCalculator = static::createStub(CartCalculator::class);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            ChannelContextRestoredEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->channelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myUser', $channelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }
}

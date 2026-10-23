<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\User\DataAbstractionLayer\UserIndexer;
use Contena\Core\System\User\DataAbstractionLayer\UserIndexingMessage;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\Subscriber\UserFlowEventsSubscriber;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserEvents;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(UserFlowEventsSubscriber::class)]
class UserFlowEventsSubscriberTest extends TestCase
{
    private Stub&EventDispatcherInterface $dispatcher;

    private Stub&ChannelContextRestorer $restorer;

    private Stub&UserIndexer $userIndexer;

    private IdsCollection $ids;

    private UserFlowEventsSubscriber $userFlowEventsSubscriber;

    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);
        $this->restorer = static::createStub(ChannelContextRestorer::class);
        $this->userIndexer = static::createStub(UserIndexer::class);
        $this->connection = static::createStub(Connection::class);

        $this->userFlowEventsSubscriber = $this->buildSubscriber();
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            UserEvents::CUSTOMER_WRITTEN_EVENT => 'onUserWritten',
        ], $this->userFlowEventsSubscriber->getSubscribedEvents());
    }

    public function testOnUserWrittenWithInstanceOfSaleChannelApi(): void
    {
        $context = Context::createDefaultContext(new ChannelApiSource(Defaults::CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $this->userFlowEventsSubscriber->onUserWritten($event);
    }

    public function testOnUserWrittenWithInstanceOfAdminApiButGettingErrorProvidedLanguageNotAvailable(): void
    {
        $this->expectException(ChannelException::class);

        $context = Context::createDefaultContext(new AdminApiSource(Defaults::CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->atLeast(1))
            ->method('getContext')
            ->willReturn($context);

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $userIndexer = $this->createMock(UserIndexer::class);
        $userIndexer->expects($this->never())
            ->method('handle');

        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByUser')
            ->willThrowException(ChannelException::providedLanguageNotAvailable('de-DE', ['en-GB']));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('delete');

        $this->buildSubscriber($dispatcher, $restorer, $userIndexer, $connection)->onUserWritten($event);
    }

    public function testOnUserWrittenWithInstanceOfAdminApiButGettingOtherError(): void
    {
        $this->expectException(ChannelException::class);

        $context = Context::createDefaultContext(new AdminApiSource(Defaults::CHANNEL_TYPE_API));

        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->atLeast(1))
            ->method('getContext')
            ->willReturn($context);

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $userIndexer = $this->createMock(UserIndexer::class);
        $userIndexer->expects($this->never())
            ->method('handle');

        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByUser')
            ->willThrowException(ChannelException::channelNotFound('sales-channel-id'));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())
            ->method('delete');

        $this->buildSubscriber($dispatcher, $restorer, $userIndexer, $connection)->onUserWritten($event);
    }

    public function testOnUserCreatedWithoutUserInContext(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('newPaymentMethod'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $this->buildSubscriber($dispatcher)->onUserWritten($event);
    }

    public function testOnUserCreatedWithUser(): void
    {
        $event = $this->createMock(EntityWrittenEvent::class);
        $event->expects($this->exactly(2))
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $payloads = [
            [
                'createdAt' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $this->ids->get('userId'),
            ],
        ];

        $event->expects($this->once())
            ->method('getPayloads')
            ->willReturn($payloads);

        $userIndexer = $this->createMock(UserIndexer::class);
        $userIndexer->expects($this->once())
            ->method('handle')
            ->with(new UserIndexingMessage([$this->ids->get('userId')]));

        $user = new UserEntity();
        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $restorer = $this->createMock(ChannelContextRestorer::class);
        $restorer->expects($this->once())
            ->method('restoreByUser')
            ->willReturn($channelContext);

        $userCreated = new UserRegisterEvent(
            $channelContext,
            $user
        );

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($userCreated);

        $this->buildSubscriber($dispatcher, $restorer, $userIndexer)->onUserWritten($event);
    }

    private function buildSubscriber(
        ?EventDispatcherInterface $dispatcher = null,
        ?ChannelContextRestorer $restorer = null,
        ?UserIndexer $userIndexer = null,
        ?Connection $connection = null,
    ): UserFlowEventsSubscriber {
        return new UserFlowEventsSubscriber(
            $dispatcher ?? $this->dispatcher,
            $restorer ?? $this->restorer,
            $userIndexer ?? $this->userIndexer,
            $connection ?? $this->connection,
        );
    }
}

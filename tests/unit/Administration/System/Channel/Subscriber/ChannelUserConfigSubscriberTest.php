<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Administration\System\Channel\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Administration\System\Channel\Subscriber\ChannelUserConfigSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelEvents;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigCollection;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 */
#[CoversClass(ChannelUserConfigSubscriber::class)]
class ChannelUserConfigSubscriberTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<UserConfigCollection>
     */
    private Stub&EntityRepository $userConfigRepository;

    private ChannelUserConfigSubscriber $channelUserConfigSubscriber;

    protected function setUp(): void
    {
        $this->userConfigRepository = static::createStub(EntityRepository::class);
        $this->channelUserConfigSubscriber = new ChannelUserConfigSubscriber($this->userConfigRepository);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ChannelEvents::CHANNEL_DELETED => 'onChannelDeleted',
        ], $this->channelUserConfigSubscriber->getSubscribedEvents());
    }

    public function testOnChannelDeletedUpsertWithEmptyArray(): void
    {
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                0,
                new UserConfigCollection([]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([], $context);
        $this->createSubscriber($repository)->onChannelDeleted($event);
    }

    public function testOnChannelDeletedUpsertWithNoChannelId(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new UserConfigCollection([$userConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([], $context);
        $this->createSubscriber($repository)->onChannelDeleted($event);
    }

    public function testOnChannelDeletedUpsertWithNoMatchingId(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue(['test' => '']);
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new UserConfigCollection([$userConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([], $context);

        $this->createSubscriber($repository)->onChannelDeleted($event);
    }

    public function testOnChannelDeletedUpsertWithMatchingId(): void
    {
        $userConfig = new UserConfigEntity();
        $userConfig->setUniqueIdentifier('user-config-id');
        $userConfig->setValue(['test' => 'test-deleted']);
        $userConfig->setId('test-deleted');
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent(
            'testEntity',
            [
                new EntityWriteResult(
                    'test-deleted',
                    [],
                    UserConfigDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT
                ),
            ],
            $context
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                1,
                new UserConfigCollection([$userConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([['id' => 'test-deleted', 'value' => []]], $context);
        $this->createSubscriber($repository)->onChannelDeleted($event);
    }

    /**
     * @param MockObject&EntityRepository<UserConfigCollection> $repository
     */
    private function createSubscriber(MockObject&EntityRepository $repository): ChannelUserConfigSubscriber
    {
        return new ChannelUserConfigSubscriber($repository);
    }
}

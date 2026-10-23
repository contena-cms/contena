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
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigCollection;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigEntity;
use Contena\Core\System\Channel\ChannelEvents;

/**
 * @internal
 */
#[CoversClass(ChannelUserConfigSubscriber::class)]
class ChannelUserConfigSubscriberTest extends TestCase
{
    /**
     * @var Stub&EntityRepository<AdminUserConfigCollection>
     */
    private Stub&EntityRepository $adminUserConfigRepository;


    protected function setUp(): void
    {
        $this->admin_userConfigRepository = static::createStub(EntityRepository::class);
        $this->channelUserConfigSubscriber = new ChannelUserConfigSubscriber($this->admin_userConfigRepository);
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
                    AdminUserConfigDefinition::ENTITY_NAME,
                    0,
                    new AdminUserConfigCollection([]),
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
        $adminUserConfig = new AdminUserConfigEntity();
        $adminUserConfig->setUniqueIdentifier('admin_user-config-id');
        // $adminUserConfig->setValue(['']);
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserConfigDefinition::ENTITY_NAME,
                1,
                new AdminUserConfigCollection([$adminUserConfig]),
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
        $adminUserConfig = new AdminUserConfigEntity();
        $adminUserConfig->setUniqueIdentifier('admin_user-config-id');
        $adminUserConfig->setValue(['test' => '']);
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent('testEntity', [], $context);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserConfigDefinition::ENTITY_NAME,
                1,
                new AdminUserConfigCollection([$adminUserConfig]),
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
        $adminUserConfig = new AdminUserConfigEntity();
        $adminUserConfig->setUniqueIdentifier('admin_user-config-id');
        $adminUserConfig->setValue(['test' => 'test-deleted']);
        $adminUserConfig->setId('test-deleted');
        $context = Context::createDefaultContext();
        $event = new EntityDeletedEvent(
            'testEntity',
            [new EntityWriteResult(
                'test-deleted',
                [],
                AdminUserConfigDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT
            ),
            ],
            $context
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                AdminUserConfigDefinition::ENTITY_NAME,
                1,
                new AdminUserConfigCollection([$adminUserConfig]),
                null,
                new Criteria(),
                $context
            ));

        $repository->expects($this->once())
            ->method('upsert')
            ->with([['id' => 'test-deleted', 'value' => []]], $context);
        $this->createSubscriber($repository)->onChannelDeleted($event);
    }

}

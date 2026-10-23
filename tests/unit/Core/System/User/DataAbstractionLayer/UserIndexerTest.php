<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\DataAbstractionLayer\UserIndexer;
use Contena\Core\System\User\DataAbstractionLayer\UserIndexingMessage;
use Contena\Core\System\User\Event\UserIndexerEvent;
use Contena\Core\Content\Newsletter\DataAbstractionLayer\Indexing\UserNewsletterChannelsUpdater;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(UserIndexer::class)]
class UserIndexerTest extends TestCase
{
    public function testUpdate(): void
    {
        $userId = Uuid::randomHex();

        $event = $this->createMock(EntityWrittenContainerEvent::class);

        $event->method('getPrimaryKeys')->willReturn(['user']);
        $event->expects($this->once())->method('getPrimaryKeysWithPropertyChange')->willReturn([
            $userId,
        ]);

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);

        $indexer = new UserIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            static::createStub(UserNewsletterChannelsUpdater::class),
            $eventDispatcher
        );

        /** @var UserIndexingMessage $indexing */
        $indexing = $indexer->update($event);

        static::assertSame($indexing->getIds(), [$userId]);
    }

    public function testHandle(): void
    {
        $userId = Uuid::randomHex();

        $message = static::createStub(UserIndexingMessage::class);
        $message->method('getData')->willReturn([$userId]);
        $message->method('getContext')->willReturn(
            static::createStub(Context::class)
        );
        $message->method('getIds')->willReturn([$userId]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->willReturnCallback(static function ($message) use ($userId) {
            static::assertInstanceOf(UserIndexerEvent::class, $message);
            static::assertSame($message->getIds(), [$userId]);

            return $message;
        });

        $indexer = new UserIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            static::createStub(UserNewsletterChannelsUpdater::class),
            $eventDispatcher
        );

        $indexer->handle($message);
    }
}

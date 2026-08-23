<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\DataAbstractionLayer\MemberIndexer;
use Contena\Core\System\Member\DataAbstractionLayer\MemberIndexingMessage;
use Contena\Core\System\Member\Event\MemberIndexerEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(MemberIndexer::class)]
class MemberIndexerTest extends TestCase
{
    public function testUpdate(): void
    {
        $memberId = Uuid::randomHex();

        $event = $this->createMock(EntityWrittenContainerEvent::class);
        $event->method('getPrimaryKeys')->willReturn(['member']);
        $event->expects($this->once())->method('getPrimaryKeysWithPropertyChange')->willReturn([$memberId]);

        $indexer = new MemberIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            static::createStub(EventDispatcherInterface::class),
        );

        $indexing = $indexer->update($event);

        static::assertInstanceOf(MemberIndexingMessage::class, $indexing);
        static::assertSame([$memberId], $indexing->getIds());
    }

    public function testHandle(): void
    {
        $memberId = Uuid::randomHex();

        $message = static::createStub(MemberIndexingMessage::class);
        $message->method('getData')->willReturn([$memberId]);
        $message->method('getContext')->willReturn(static::createStub(Context::class));
        $message->method('getIds')->willReturn([$memberId]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch')->willReturnCallback(static function (object $event) use ($memberId): object {
            static::assertInstanceOf(MemberIndexerEvent::class, $event);
            static::assertSame([$memberId], $event->getIds());

            return $event;
        });

        $indexer = new MemberIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $eventDispatcher,
        );

        $indexer->handle($message);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Category\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\Subscriber\CategoryTreeMovedSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @internal
 */
#[CoversClass(CategoryTreeMovedSubscriber::class)]
class CategoryTreeMovedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $events = CategoryTreeMovedSubscriber::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertArrayHasKey('Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent', $events);
        static::assertSame('detectChannelEntryPoints', $events['Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent']);
    }

    public function testNotRootChange(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->never())->method('sendIndexingMessage');
        $subscriber = new CategoryTreeMovedSubscriber($registry);

        $event = new EntityWrittenContainerEvent(Context::createCLIContext(), new NestedEventCollection(), []);
        $subscriber->detectChannelEntryPoints($event);
    }

    public function testDetectChannelEntryPoints(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->once())->method('sendIndexingMessage')->with(['category.indexer', 'blog.indexer']);
        $subscriber = new CategoryTreeMovedSubscriber($registry);

        $event = new EntityWrittenEvent(
            ChannelDefinition::ENTITY_NAME,
            [
                new EntityWriteResult('test', ['navigationCategoryId' => 'asd'], ChannelDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
            ],
            Context::createCLIContext()
        );

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([$event]),
            []
        );

        $subscriber->detectChannelEntryPoints($event);
    }
}

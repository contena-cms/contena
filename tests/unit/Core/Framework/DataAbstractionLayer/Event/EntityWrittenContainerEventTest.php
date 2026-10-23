<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(EntityWrittenContainerEvent::class)]
class EntityWrittenContainerEventTest extends TestCase
{
    public function testReturnsWriteResultsForEntity(): void
    {
        $context = Context::createDefaultContext();
        $writeResult = new EntityWriteResult('blog-id', [], 'blog', EntityWriteResult::OPERATION_INSERT);
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('blog', [$writeResult], $context)]),
            [],
        );

        static::assertSame([$writeResult], $event->getResults('blog')->getElements());
        static::assertTrue($event->getResults('category')->isEmpty());
    }

    public function testAggregatesWriteResultsFromMultipleEventsForEntity(): void
    {
        $context = Context::createDefaultContext();
        $delete = new EntityWriteResult('deleted-blog-id', [], 'blog', EntityWriteResult::OPERATION_DELETE);
        $update = new EntityWriteResult('updated-blog-id', ['childCount' => 1], 'blog', EntityWriteResult::OPERATION_UPDATE);
        $category = new EntityWriteResult('category-id', [], 'category', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityDeletedEvent('blog', [$delete], $context),
                new EntityWrittenEvent('category', [$category], $context),
                new EntityWrittenEvent('blog', [$update], $context),
            ]),
            [],
        );

        static::assertSame([$delete, $update], $event->getResults('blog')->getElements());
        static::assertSame(['deleted-blog-id', 'updated-blog-id'], $event->getPrimaryKeys('blog'));
        static::assertSame(['deleted-blog-id'], $event->getDeletedPrimaryKeys('blog'));
        static::assertSame(['updated-blog-id'], $event->getPrimaryKeysWithPropertyChange('blog', ['childCount']));
    }

    public function testIgnoresOtherNestedEventsAndHandlesEmptyFilters(): void
    {
        $context = Context::createDefaultContext();
        $update = new EntityWriteResult('updated-blog-id', ['active' => null], 'blog', EntityWriteResult::OPERATION_UPDATE);
        $nestedEvents = new NestedEventCollection([
            new class($context) extends NestedEvent {
                public function __construct(private readonly Context $context)
                {
                }

                public function getContext(): Context
                {
                    return $this->context;
                }
            },
            new EntityWrittenEvent('blog', [$update], $context),
        ]);

        /** @phpstan-ignore argument.type (Intentionally passes a non-written event to cover the defensive runtime guard.) */
        $event = new EntityWrittenContainerEvent($context, $nestedEvents, []);

        static::assertSame([$update], $event->getResults('blog')->getElements());
        static::assertSame(['updated-blog-id'], $event->getPrimaryKeysWithPropertyChange('blog', ['active']));
        static::assertSame([], $event->getPrimaryKeysWithPropertyChange('blog', []));
        static::assertSame([], $event->getDeletedPrimaryKeys('blog'));
        static::assertSame([], $event->getPrimaryKeys('missing'));
    }

    public function testExposesContainerStateAndFindsEvents(): void
    {
        $context = Context::createDefaultContext();
        $blogResult = new EntityWriteResult('blog-id', [], 'blog', EntityWriteResult::OPERATION_INSERT);
        $categoryResult = new EntityWriteResult('category-id', [], 'category', EntityWriteResult::OPERATION_INSERT);
        $blogEvent = new EntityWrittenEvent('blog', [$blogResult], $context);
        $container = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([$blogEvent]),
            ['write-error'],
        );

        static::assertSame($context, $container->getContext());
        static::assertSame(['write-error'], $container->getErrors());
        static::assertSame($blogEvent, $container->getEventByEntityName('blog'));
        static::assertNull($container->getEventByEntityName('category'));
        static::assertSame(['blog.written' => ['blog-id']], $container->getList());
        static::assertFalse($container->isCloned());

        $categoryEvent = new EntityWrittenEvent('category', [$categoryResult], $context);
        $container->addEvent($categoryEvent);
        $container->setCloned(true);

        static::assertSame($categoryEvent, $container->getEventByEntityName('category'));
        static::assertSame(
            ['blog.written' => ['blog-id'], 'category.written' => ['category-id']],
            $container->getList(),
        );
        static::assertTrue($container->isCloned());
    }

    public function testCreatesWrittenAndDeletedEvents(): void
    {
        $context = Context::createDefaultContext();
        $insert = new EntityWriteResult('insert-id', [], 'blog', EntityWriteResult::OPERATION_INSERT);
        $delete = new EntityWriteResult('delete-id', [], 'category', EntityWriteResult::OPERATION_DELETE);

        $written = EntityWrittenContainerEvent::createWithWrittenEvents(
            ['blog' => [$insert], 'empty' => []],
            $context,
            ['write-error'],
            true,
        );
        $deleted = EntityWrittenContainerEvent::createWithDeletedEvents(['category' => [$delete]], $context, []);

        static::assertSame([$insert], $written->getResults('blog')->getElements());
        static::assertSame(['write-error'], $written->getErrors());
        static::assertTrue($written->isCloned());
        static::assertInstanceOf(EntityDeletedEvent::class, $deleted->getEventByEntityName('category'));
        static::assertSame(['delete-id'], $deleted->getDeletedPrimaryKeys('category'));
    }

    public function testReturnsPrimaryKeysWithPayloadIgnoringFields(): void
    {
        $context = Context::createDefaultContext();
        $delete = new EntityWriteResult('delete-id', [], 'blog', EntityWriteResult::OPERATION_DELETE);
        $ignoredUpdate = new EntityWriteResult('ignored-id', ['updatedAt' => 'date'], 'blog', EntityWriteResult::OPERATION_UPDATE);
        $changedUpdate = new EntityWriteResult(
            'changed-id',
            ['updatedAt' => 'date', 'active' => true],
            'blog',
            EntityWriteResult::OPERATION_UPDATE,
        );
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([new EntityWrittenEvent('blog', [$delete, $ignoredUpdate, $changedUpdate], $context)]),
            [],
        );

        static::assertSame(
            ['delete-id', 'changed-id'],
            $event->getPrimaryKeysWithPayloadIgnoringFields('blog', ['updatedAt']),
        );
    }
}

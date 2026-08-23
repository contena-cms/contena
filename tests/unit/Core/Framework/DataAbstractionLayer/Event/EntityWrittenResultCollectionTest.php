<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;

/**
 * @internal
 */
#[CoversClass(EntityWrittenContainerEvent::class)]
#[CoversClass(EntityWrittenEvent::class)]
class EntityWrittenResultCollectionTest extends TestCase
{
    public function testReturnsWriteResultsFromAnEntityEvent(): void
    {
        $insert = new EntityWriteResult('insert-id', [], 'entity', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', [], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('entity', [$insert, $update], Context::createDefaultContext());

        static::assertSame([$insert, $update], $event->getResults()->getElements());
        static::assertSame([1 => $update], $event->getResults()->only(EntityWriteResult::OPERATION_UPDATE)->getElements());
        static::assertSame([$insert, $update], $event->getWriteResults());
    }

    public function testAggregatesAndFiltersWriteResultsFromAContainerEvent(): void
    {
        $context = Context::createDefaultContext();
        $delete = new EntityWriteResult('deleted-id', [], 'entity', EntityWriteResult::OPERATION_DELETE);
        $update = new EntityWriteResult('updated-id', ['parentId' => 'parent-id'], 'entity', EntityWriteResult::OPERATION_UPDATE);
        $other = new EntityWriteResult('other-id', [], 'other', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenContainerEvent(
            $context,
            new NestedEventCollection([
                new EntityDeletedEvent('entity', [$delete], $context),
                new EntityWrittenEvent('other', [$other], $context),
                new EntityWrittenEvent('entity', [$update], $context),
            ]),
            [],
        );

        static::assertSame([$delete, $update], $event->getResults('entity')->getElements());
        static::assertSame(['deleted-id', 'updated-id'], $event->getPrimaryKeys('entity'));
        static::assertSame(['deleted-id'], $event->getDeletedPrimaryKeys('entity'));
        static::assertSame(['updated-id'], $event->getPrimaryKeysWithPropertyChange('entity', ['parentId']));
        static::assertTrue($event->getResults('missing')->isEmpty());
    }
}

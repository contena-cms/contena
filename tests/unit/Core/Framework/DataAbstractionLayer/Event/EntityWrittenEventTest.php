<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(EntityWrittenEvent::class)]
class EntityWrittenEventTest extends TestCase
{
    public function testReturnsWriteResultsAsCollection(): void
    {
        $insert = new EntityWriteResult('insert-id', [], 'blog', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', [], 'blog', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('blog', [$insert, $update], Context::createDefaultContext());

        static::assertSame([$insert, $update], $event->getResults()->getElements());
        static::assertSame([1 => $update], $event->getResults()->only(EntityWriteResult::OPERATION_UPDATE)->getElements());
        static::assertSame([$insert, $update], $event->getWriteResults());
    }

    public function testReturnsEmptyCollectionWithoutWriteResults(): void
    {
        $event = new EntityWrittenEvent('blog', [], Context::createDefaultContext());

        static::assertTrue($event->getResults()->isEmpty());
    }

    public function testExposesEventData(): void
    {
        $context = Context::createDefaultContext();
        $insert = new EntityWriteResult('insert-id', ['name' => 'Example'], 'blog', EntityWriteResult::OPERATION_INSERT);
        $update = new EntityWriteResult('update-id', ['active' => false], 'blog', EntityWriteResult::OPERATION_UPDATE);
        $event = new EntityWrittenEvent('blog', [$insert, $update], $context, ['write-error']);

        static::assertSame('blog.written', $event->getName());
        static::assertSame('blog', $event->getEntityName());
        static::assertSame($context, $event->getContext());
        static::assertSame(['write-error'], $event->getErrors());
        static::assertSame(['insert-id', 'update-id'], $event->getIds());
        static::assertSame(['insert-id', 'update-id'], $event->getIds());
        static::assertSame([['name' => 'Example'], ['active' => false]], $event->getPayloads());
        static::assertSame([['name' => 'Example'], ['active' => false]], $event->getPayloads());
    }
}

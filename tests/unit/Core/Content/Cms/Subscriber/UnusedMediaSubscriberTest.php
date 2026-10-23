<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(UnusedMediaSubscriber::class)]
class UnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            UnusedMediaSubscriber::getSubscribedEvents()
        );
    }

    public function testNothingIsQueriedWhenTheBatchIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $event = new UnusedMediaSearchEvent([], Context::createDefaultContext());

        (new UnusedMediaSubscriber($connection))->removeUsedMedia($event);

        static::assertSame([], $event->getUnusedIds());
    }
}

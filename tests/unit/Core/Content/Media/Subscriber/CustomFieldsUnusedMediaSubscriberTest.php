<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\UnusedMediaSearchEvent;
use Contena\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

/**
 * @internal
 */
#[CoversClass(CustomFieldsUnusedMediaSubscriber::class)]
class CustomFieldsUnusedMediaSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                UnusedMediaSearchEvent::class => 'removeUsedMedia',
            ],
            CustomFieldsUnusedMediaSubscriber::getSubscribedEvents()
        );
    }

    public function testNothingIsQueriedWhenTheBatchIsEmpty(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $connection->expects($this->never())->method('fetchFirstColumn');
        $connection->expects($this->never())->method('executeQuery');

        $subscriber = new CustomFieldsUnusedMediaSubscriber(
            $connection,
            static::createStub(DefinitionInstanceRegistry::class)
        );

        $event = new UnusedMediaSearchEvent([], Context::createDefaultContext());

        $subscriber->removeUsedMedia($event);

        static::assertSame([], $event->getUnusedIds());
    }
}

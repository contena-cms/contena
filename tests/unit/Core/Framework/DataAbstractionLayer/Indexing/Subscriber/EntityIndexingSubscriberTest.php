<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\EntityIndexingSubscriber;
use Contena\Core\Framework\Event\NestedEventCollection;

/**
 * @internal
 */
#[CoversClass(EntityIndexingSubscriber::class)]
class EntityIndexingSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertArrayHasKey(EntityWrittenContainerEvent::class, EntityIndexingSubscriber::getSubscribedEvents());
    }

    public function testRefresh(): void
    {
        $registry = $this->createMock(EntityIndexerRegistry::class);
        $registry->expects($this->once())->method('refresh');

        $subscriber = new EntityIndexingSubscriber($registry);
        $subscriber->refreshIndex(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));
    }
}

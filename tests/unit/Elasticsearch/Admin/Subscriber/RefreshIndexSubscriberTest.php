<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Elasticsearch\Admin\AdminIndexingBehavior;
use Contena\Elasticsearch\Admin\AdminSearchRegistry;
use Contena\Elasticsearch\Admin\Subscriber\RefreshIndexSubscriber;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(RefreshIndexSubscriber::class)]
class RefreshIndexSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertArrayHasKey(RefreshIndexEvent::class, RefreshIndexSubscriber::getSubscribedEvents());
    }

    public function testHandedWithSkipOption(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('iterate')->with(new AdminIndexingBehavior(false, ['product']));

        $subscriber = new RefreshIndexSubscriber($registry);
        $subscriber->handled(new RefreshIndexEvent(false, ['product']));
    }

    public function testHandedWithOnlyOption(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects($this->once())->method('iterate')->with(new AdminIndexingBehavior(false, [], ['product']));

        $subscriber = new RefreshIndexSubscriber($registry);
        $subscriber->handled(new RefreshIndexEvent(false, [], ['product']));
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\DataAbstractionLayer\StatesUpdater;
use Contena\Core\Content\Blog\Subscriber\RepairDigitalBlogStatesSubscriber;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;

/**
 * @internal
 */
#[CoversClass(RepairDigitalBlogStatesSubscriber::class)]
#[DisabledFeatures(['v6.8.0.0'])]
class RepairDigitalBlogStatesSubscriberTest extends TestCase
{
    public function testNoSubscribersInMajorMode(): void
    {
        Feature::withFeatureEnabled('v6.8.0.0', static function (): void {
            static::assertSame([], RepairDigitalBlogStatesSubscriber::getSubscribedEvents());
        });
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testDirectInvocationThrowsBeforeRepairInMajorMode(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');
        $subscriber = new RepairDigitalBlogStatesSubscriber($connection, null, new ArrayKeyValueStorage(), static::createStub(LoggerInterface::class));
        $event = $this->createEvent();

        $this->expectException(FeatureException::class);
        Feature::withFeatureEnabled('v6.8.0.0', static fn () => $subscriber->repair($event));
    }

    public function testRepairReturnsEarlyWhenAlreadyMarked(): void
    {
        $storage = new ArrayKeyValueStorage([
            RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES => '1',
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->never())->method('update');

        $subscriber = new RepairDigitalBlogStatesSubscriber(
            $connection,
            $statesUpdater,
            $storage,
            static::createStub(LoggerInterface::class),
        );

        $subscriber->repair($this->createEvent());

        static::assertTrue($storage->has(RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES));
        static::assertSame('1', $storage->get(RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES));
    }

    public function testRepairUpdatesBlogsAndSetsMarker(): void
    {
        $ids = ['a', 'b'];
        $event = $this->createEvent();

        $storage = new ArrayKeyValueStorage();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($ids);

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->once())
            ->method('update')
            ->with($ids, $event->getContext());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('error');

        $subscriber = new RepairDigitalBlogStatesSubscriber(
            $connection,
            $statesUpdater,
            $storage,
            $logger,
        );

        $subscriber->repair($event);

        static::assertStringContainsString('Repaired blog states for 2 digital blog(s)', $event->getPostUpdateMessage());
        static::assertTrue($storage->has(RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES));
        static::assertSame('1', $storage->get(RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES));
    }

    public function testRepairAbortsWhenSameChunkIsFetchedTwice(): void
    {
        $ids = array_map(static fn (int $i): string => 'id-' . $i, range(1, 500));
        $event = $this->createEvent();

        $storage = new ArrayKeyValueStorage();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls($ids, $ids);

        $statesUpdater = $this->createMock(StatesUpdater::class);
        $statesUpdater->expects($this->once())
            ->method('update')
            ->with($ids, $event->getContext());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('same blog chunk was fetched repeatedly'),
                static::arrayHasKey('blogIds')
            );

        $subscriber = new RepairDigitalBlogStatesSubscriber(
            $connection,
            $statesUpdater,
            $storage,
            $logger,
        );

        $subscriber->repair($event);

        static::assertStringContainsString('Repaired blog states for 500 digital blog(s)', $event->getPostUpdateMessage());
        static::assertTrue($storage->has(RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES));
        static::assertSame('1', $storage->get(RepairDigitalBlogStatesSubscriber::REPAIRED_DIGITAL_BLOG_STATES));
    }

    private function createEvent(): UpdatePostFinishEvent
    {
        return new UpdatePostFinishEvent(new Context(new SystemSource()), '6.7.8.0', '6.7.8.1');
    }
}

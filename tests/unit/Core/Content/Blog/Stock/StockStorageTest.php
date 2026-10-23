<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogBackInStockEvent;
use Contena\Core\Content\Blog\Events\BlogNoLongerAvailableEvent;
use Contena\Core\Content\Blog\Stock\StockLoadRequest;
use Contena\Core\Content\Blog\Stock\StockStorage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(StockStorage::class)]
class StockStorageTest extends TestCase
{
    public function testLoadDoesNothing(): void
    {
        $ids = new IdsCollection();

        $blogIds = $ids->getList(['p-1', 'p-2', 'p-3']);
        $channelContext = static::createStub(ChannelContext::class);

        $connection = static::createStub(Connection::class);
        $dispatcher = static::createStub(EventDispatcherInterface::class);

        $stockStorage = new StockStorage($connection, $dispatcher);

        static::assertSame(
            [],
            $stockStorage->load(new StockLoadRequest(array_values($blogIds)), $channelContext)->all()
        );
    }

    public function testEmptyChangesDoNotDispatchEvent(): void
    {
        $connection = static::createStub(Connection::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        $dispatcher->expects($this->never())->method('dispatch');

        $stockStorage = new StockStorage($connection, $dispatcher);
        $stockStorage->alter([], Context::createDefaultContext());
    }

    public function testIndexDispatchesEventsByAvailabilityDirection(): void
    {
        $ids = new IdsCollection();
        $dispatcher = new CollectingEventDispatcher();

        $stockStorage = new StockStorage($this->createAvailabilityConnection($ids), $dispatcher);
        $stockStorage->index(array_values($ids->getList(['lost', 'gained', 'unchanged'])), Context::createDefaultContext());

        $noLongerAvailable = $dispatcher->getEventsOfClass(BlogNoLongerAvailableEvent::class);
        static::assertCount(1, $noLongerAvailable);
        static::assertSame([$ids->get('lost')], $noLongerAvailable[0]->getIds());

        $backInStock = $dispatcher->getEventsOfClass(BlogBackInStockEvent::class);
        static::assertCount(1, $backInStock);
        static::assertSame([$ids->get('gained')], $backInStock[0]->getIds());
    }

    public function testIndexDispatchesNoEventWithoutAvailabilityChange(): void
    {
        $ids = new IdsCollection();
        $dispatcher = new CollectingEventDispatcher();

        $before = [$ids->get('p-1') => '1', $ids->get('p-2') => '0'];

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn($before, $before);

        $stockStorage = new StockStorage($connection, $dispatcher);
        $stockStorage->index(array_keys($before), Context::createDefaultContext());

        static::assertSame([], $dispatcher->getEvents());
    }

    private function createAvailabilityConnection(IdsCollection $ids): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(
            [$ids->get('lost') => '1', $ids->get('gained') => '0', $ids->get('unchanged') => '1'],
            [$ids->get('lost') => '0', $ids->get('gained') => '1', $ids->get('unchanged') => '1'],
        );

        return $connection;
    }
}

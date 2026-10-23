<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Stock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogDefinition;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Stock\AbstractStockStorage;
use Contena\Core\Content\Blog\Stock\LoadBlogStockSubscriber;
use Contena\Core\Content\Blog\Stock\StockData;
use Contena\Core\Content\Blog\Stock\StockDataCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelEntityLoadedEvent;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[CoversClass(LoadBlogStockSubscriber::class)]
class LoadBlogStockSubscriberTest extends TestCase
{
    public function testStockDataIsAppliedFromStorage(): void
    {
        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $subscriber = new LoadBlogStockSubscriber($stockStorage);

        $ids = new IdsCollection();

        $p1 = (new ChannelBlogEntity())->assign(['id' => $ids->get('blog-1')]);
        $p2 = (new ChannelBlogEntity())->assign(['id' => $ids->get('blog-2')]);

        $stock1 = new StockData($ids->get('blog-1'), 10, false, 5, null, null);
        $stock1->addArrayExtension('extra', ['arbitrary-data1' => 'foo']);
        $stock2 = new StockData($ids->get('blog-2'), 12, true);

        $stockStorage->expects($this->once())
            ->method('load')
            ->willReturn(new StockDataCollection([$stock1, $stock2]));

        /** @var ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new ChannelEntityLoadedEvent(
            static::createStub(ChannelBlogDefinition::class),
            [$p1, $p2],
            static::createStub(ChannelContext::class)
        );

        $subscriber->channelLoaded($event);

        static::assertSame(10, $p1->getStock());
        static::assertFalse($p1->getAvailable());
        static::assertSame(5, $p1->getMinPurchase());
        static::assertTrue($p1->hasExtension('stock_data'));
        static::assertSame($stock1, $p1->getExtension('stock_data'));

        static::assertSame(12, $p2->getStock());
        static::assertTrue($p2->getAvailable());
        static::assertNull($p2->getMinPurchase());
        static::assertTrue($p2->hasExtension('stock_data'));
        static::assertSame($stock2, $p2->getExtension('stock_data'));
    }

    public function testStockDataIsAppliedFromStorageWithPartialEntities(): void
    {
        $stockStorage = $this->createMock(AbstractStockStorage::class);
        $subscriber = new LoadBlogStockSubscriber($stockStorage);

        $ids = new IdsCollection();

        $p1 = (new PartialEntity())->assign(['id' => $ids->get('blog-1')]);
        $p2 = (new PartialEntity())->assign(['id' => $ids->get('blog-2')]);

        $stock1 = new StockData($ids->get('blog-1'), 10, false, 5, null, null);
        $stock1->addArrayExtension('extra', ['arbitrary-data1' => 'foo']);
        $stock2 = new StockData($ids->get('blog-2'), 12, true);

        $stockStorage->expects($this->once())
            ->method('load')
            ->willReturn(new StockDataCollection([$stock1, $stock2]));

        /** @var ChannelEntityLoadedEvent<BlogEntity|PartialEntity> $event */
        $event = new ChannelEntityLoadedEvent(
            static::createStub(ChannelBlogDefinition::class),
            [$p1, $p2],
            static::createStub(ChannelContext::class)
        );

        $subscriber->channelLoaded($event);

        static::assertSame(10, $p1->get('stock'));
        static::assertFalse($p1->get('available'));
        static::assertSame(5, $p1->get('minPurchase'));
        static::assertTrue($p1->hasExtension('stock_data'));
        static::assertSame($stock1, $p1->getExtension('stock_data'));

        static::assertSame(12, $p2->get('stock'));
        static::assertTrue($p2->get('available'));
        static::assertNull($p2->get('minPurchase'));
        static::assertTrue($p2->hasExtension('stock_data'));
        static::assertSame($stock2, $p2->getExtension('stock_data'));
    }
}

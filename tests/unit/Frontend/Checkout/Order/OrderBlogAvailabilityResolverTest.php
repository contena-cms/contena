<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Checkout\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilter;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Frontend\Checkout\Order\OrderBlogAvailabilityResolver;

/**
 * @internal
 */
#[CoversClass(OrderBlogAvailabilityResolver::class)]
class OrderBlogAvailabilityResolverTest extends TestCase
{
    public function testOnlyReachableBlogsAreFlaggedVisible(): void
    {
        $visibleId = Uuid::randomHex();
        $hiddenId = Uuid::randomHex();

        $order = $this->createOrder([
            $this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $visibleId),
            $this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $hiddenId),
        ]);

        $this->createResolver([$visibleId])->addAvailability([$order], Generator::generateChannelContext());

        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        foreach ($lineItems as $lineItem) {
            $extension = $lineItem->getExtension(OrderBlogAvailabilityResolver::LINE_ITEM_EXTENSION);
            static::assertInstanceOf(ArrayStruct::class, $extension);
            static::assertSame($lineItem->getBlogId() === $visibleId, $extension->get('visible'));
        }
    }

    public function testDeletedBlogsAreNeverQueriedAndAreNotVisible(): void
    {
        $lineItem = $this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, null);
        $order = $this->createOrder([$lineItem]);

        $route = static::createMock(ChannelRepository::class);
        $route->expects($this->never())->method('searchIds');

        $this->createResolver([], $route)->addAvailability([$order], Generator::generateChannelContext());

        $extension = $lineItem->getExtension(OrderBlogAvailabilityResolver::LINE_ITEM_EXTENSION);
        static::assertInstanceOf(ArrayStruct::class, $extension);
        static::assertFalse($extension->get('visible'));
    }

    public function testNonBlogLineItemsAreIgnored(): void
    {
        $promotion = $this->createLineItem(LineItem::PROMOTION_LINE_ITEM_TYPE, Uuid::randomHex());
        $order = $this->createOrder([$promotion]);

        $route = static::createMock(ChannelRepository::class);
        // an order without blogs must not run an unfiltered, unlimited criteria over the catalogue
        $route->expects($this->never())->method('searchIds');

        $this->createResolver([], $route)->addAvailability([$order], Generator::generateChannelContext());

        static::assertNull($promotion->getExtension(OrderBlogAvailabilityResolver::LINE_ITEM_EXTENSION));
    }

    public function testAllOrdersAreResolvedWithASingleCall(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $orders = [
            $this->createOrder([$this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $first)]),
            $this->createOrder([$this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $second)]),
            $this->createOrder([$this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $first)]),
        ];

        $route = static::createMock(ChannelRepository::class);
        $route
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria) use ($first, $second): bool {
                static::assertEqualsCanonicalizing([$first, $second], array_values($criteria->getIds()));

                return true;
            }))
            ->willReturn($this->createResponse([$first]));

        $this->createResolver([$first], $route)->addAvailability($orders, Generator::generateChannelContext());

        static::assertTrue($this->firstExtension($orders[0])->get('visible'));
        static::assertFalse($this->firstExtension($orders[1])->get('visible'));
    }

    public function testTheCloseoutFilterIsOnlyAddedWhenTheConfigIsOn(): void
    {
        $blogId = Uuid::randomHex();
        $order = $this->createOrder([$this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $blogId)]);

        $route = static::createMock(ChannelRepository::class);
        $route
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria): bool {
                static::assertCount(1, $criteria->getFilters());
                static::assertInstanceOf(BlogCloseoutFilter::class, $criteria->getFilters()[0]);

                return true;
            }))
            ->willReturn($this->createResponse([$blogId]));

        $resolver = $this->createResolver([$blogId], $route, closeoutHidden: true);
        $resolver->addAvailability([$order], Generator::generateChannelContext());
    }

    public function testTheCloseoutFilterIsNotAddedWhenTheConfigIsOff(): void
    {
        $blogId = Uuid::randomHex();
        $order = $this->createOrder([$this->createLineItem(LineItem::BLOG_LINE_ITEM_TYPE, $blogId)]);

        $route = static::createMock(ChannelRepository::class);
        $route
            ->expects($this->once())
            ->method('searchIds')
            ->with(static::callback(static function (Criteria $criteria): bool {
                static::assertSame([], $criteria->getFilters());

                return true;
            }))
            ->willReturn($this->createResponse([$blogId]));

        $this->createResolver([$blogId], $route)->addAvailability([$order], Generator::generateChannelContext());
    }

    private function firstExtension(OrderEntity $order): ArrayStruct
    {
        $lineItems = $order->getLineItems();
        static::assertNotNull($lineItems);

        $extension = $lineItems->first()?->getExtension(OrderBlogAvailabilityResolver::LINE_ITEM_EXTENSION);
        static::assertInstanceOf(ArrayStruct::class, $extension);

        return $extension;
    }

    /**
     * @param list<string> $visibleIds
     * @param ChannelRepository<BlogCollection>|null $route
     */
    private function createResolver(
        array $visibleIds,
        ?ChannelRepository $route = null,
        bool $closeoutHidden = false
    ): OrderBlogAvailabilityResolver {
        if ($route === null) {
            $route = static::createStub(ChannelRepository::class);
            $route->method('searchIds')->willReturn($this->createResponse($visibleIds));
        }

        $closeoutFilterFactory = static::createStub(AbstractBlogCloseoutFilterFactory::class);
        $closeoutFilterFactory->method('create')->willReturn(new BlogCloseoutFilter());

        $config = new StaticSystemConfigService([
            'core.listing.hideCloseoutBlogsWhenOutOfStock' => $closeoutHidden,
        ]);

        return new OrderBlogAvailabilityResolver($route, $config, $closeoutFilterFactory);
    }

    /**
     * @param list<string> $visibleIds
     */
    private function createResponse(array $visibleIds): IdSearchResult
    {
        $data = [];

        foreach ($visibleIds as $id) {
            $data[$id] = ['primaryKey' => $id, 'data' => []];
        }

        return new IdSearchResult(\count($data), $data, new Criteria(), Context::createDefaultContext());
    }

    /**
     * @param list<OrderLineItemEntity> $lineItems
     */
    private function createOrder(array $lineItems): OrderEntity
    {
        $order = new OrderEntity();
        $id = Uuid::randomHex();
        $order->setId($id);
        $order->setUniqueIdentifier($id);
        $order->setLineItems(new OrderLineItemCollection($lineItems));

        return $order;
    }

    private function createLineItem(string $type, ?string $blogId): OrderLineItemEntity
    {
        $lineItem = new OrderLineItemEntity();
        $id = Uuid::randomHex();
        $lineItem->setId($id);
        $lineItem->setUniqueIdentifier($id);
        $lineItem->setType($type);
        $lineItem->setLabel('line item');
        $lineItem->setQuantity(1);
        $lineItem->setGood(true);
        $lineItem->setBlogId($blogId);
        $lineItem->setReferencedId($blogId);

        return $lineItem;
    }
}

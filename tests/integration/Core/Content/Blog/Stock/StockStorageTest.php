<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Stock;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Events\BlogBackInStockEvent;
use Contena\Core\Content\Blog\Events\BlogNoLongerAvailableEvent;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;

/**
 * @internal
 */
class StockStorageTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<OrderLineItemCollection>
     */
    private EntityRepository $lineItemRepository;


    /**
     * @var list<string>
     */
    private array $noLongerAvailableIds = [];

    /**
     * @var list<string>
     */
    private array $backInStockIds = [];

    private AbstractChannelContextFactory $contextFactory;

    private ChannelContext $context;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->lineItemRepository = static::getContainer()->get('order_line_item.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->addCountriesToChannel();

        $this->context = $this->contextFactory->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser(),
            ]
        );
    }

    public function testAvailableOnInsert(): void
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$blog], $context);
        $this->addTaxDataToChannel($this->context, $blog['tax']);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());

        $this->assertStock(10, $blog);
    }

    public function testAvailableWithoutStock(): void
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 0,
            'isCloseout' => true,
            'name' => 'Test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$blog], $context);
        $this->addTaxDataToChannel($this->context, $blog['tax']);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getIsCloseout());
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);
    }

    public function testAvailableAfterUpdate(): void
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create([$blog], $context);
        $this->addTaxDataToChannel($this->context, $blog['tax']);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(10, $blog);

        $this->blogRepository->update([['id' => $id, 'stock' => 0]], $context);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getIsCloseout());
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);
    }

    public function testAvailableAfterUpdateIsCloseoutNull(): void
    {
        $parentId = $this->createBlog([
            'isCloseout' => null,
        ]);
        $blogId = $this->createBlog([
            'parentId' => $parentId,
            'stock' => 10,
            'isCloseout' => true,
        ]);

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->get($blogId);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(10, $blog);

        $this->blogRepository->update([['id' => $blogId, 'isCloseout' => null]], $context);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->get($blogId);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->getIsCloseout());
        static::assertTrue($blog->getAvailable());
        $this->assertStock(10, $blog);
    }

    public function testVariantAvailabilityFollowsCloseoutChangeOfParent(): void
    {
        $parentId = $this->createBlog([
            'stock' => 0,
            'isCloseout' => true,
        ]);
        $variantId = $this->createBlog([
            'parentId' => $parentId,
            'stock' => 0,
            'isCloseout' => null,
        ]);

        $context = Context::createDefaultContext();

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertFalse($variant->getAvailable());

        $this->blogRepository->update([['id' => $parentId, 'isCloseout' => false]], $context);

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertTrue($variant->getAvailable());

        $this->blogRepository->update([['id' => $parentId, 'isCloseout' => true]], $context);

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertFalse($variant->getAvailable());
    }

    public function testVariantAvailabilityFollowsMinPurchaseChangeOfParent(): void
    {
        $parentId = $this->createBlog([
            'stock' => 0,
            'isCloseout' => true,
            'minPurchase' => 1,
        ]);
        $variantId = $this->createBlog([
            'parentId' => $parentId,
            'stock' => 1,
            'isCloseout' => null,
        ]);

        $context = Context::createDefaultContext();

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertTrue($variant->getAvailable());

        $this->blogRepository->update([['id' => $parentId, 'minPurchase' => 2]], $context);

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertFalse($variant->getAvailable());
    }

    public function testVariantWithOwnCloseoutFollowsMinPurchaseChangeOfParent(): void
    {
        $parentId = $this->createBlog([
            'stock' => 0,
            'isCloseout' => false,
            'minPurchase' => 1,
        ]);
        $variantId = $this->createBlog([
            'parentId' => $parentId,
            'stock' => 1,
            'isCloseout' => true,
        ]);

        $context = Context::createDefaultContext();

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertTrue($variant->getAvailable());

        $this->blogRepository->update([['id' => $parentId, 'minPurchase' => 2]], $context);

        $variant = $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->get($variantId);
        static::assertInstanceOf(BlogEntity::class, $variant);
        static::assertFalse($variant->getAvailable());
    }

    public static function availabilityEventsOnCreateProvider(): \Generator
    {
        yield 'Closeout, no stock' => [0, true, false];
        yield 'Closeout, with stock' => [1, true, true];
        yield 'None closeout, no stock' => [0, false, true];
        yield 'None closeout, stock' => [1, false, true];
    }

    #[DataProvider('availabilityEventsOnCreateProvider')]
    public function testAvailabilityEventsOnCreate(int $stock, bool $closeout, bool $backInStock): void
    {
        $ids = new IdsCollection();

        $this->collectAvailabilityEvents();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(10)
            ->stock($stock)
            ->closeout($closeout)
            ->build();

        $this->blogRepository->create([$blog], Context::createDefaultContext());

        $this->assertAvailabilityEvents($ids->get('p1'), noLongerAvailable: false, backInStock: $backInStock);
    }

    public static function availabilityEventsOnAlterProvider(): \Generator
    {
        yield 'Closeout, not stock, after 0' => [
            'stock' => 0,
            'closeout' => true,
            'after' => 0,
            'noLongerAvailable' => false,
            'backInStock' => false,
        ];

        yield 'Closeout, not stock, after 1' => [
            'stock' => 0,
            'closeout' => true,
            'after' => 1,
            'noLongerAvailable' => false,
            'backInStock' => true,
        ];

        yield 'Closeout, stock, after 0' => [
            'stock' => 1,
            'closeout' => true,
            'after' => 0,
            'noLongerAvailable' => true,
            'backInStock' => false,
        ];

        yield 'Closeout, stock, after 1' => [
            'stock' => 1,
            'closeout' => true,
            'after' => 1,
            'noLongerAvailable' => false,
            'backInStock' => false,
        ];

        // changing stock of none closeout blogs should never change the availability
        yield 'None closeout, not stock, after 0' => [
            'stock' => 0,
            'closeout' => false,
            'after' => 0,
            'noLongerAvailable' => false,
            'backInStock' => false,
        ];

        yield 'None closeout, not stock, after 1' => [
            'stock' => 0,
            'closeout' => false,
            'after' => 1,
            'noLongerAvailable' => false,
            'backInStock' => false,
        ];

        yield 'None closeout, stock, after 0' => [
            'stock' => 1,
            'closeout' => false,
            'after' => 0,
            'noLongerAvailable' => false,
            'backInStock' => false,
        ];

        yield 'None closeout, stock, after 1' => [
            'stock' => 1,
            'closeout' => false,
            'after' => 1,
            'noLongerAvailable' => false,
            'backInStock' => false,
        ];
    }

    #[DataProvider('availabilityEventsOnAlterProvider')]
    public function testAvailabilityEventsOnAlter(int $stock, bool $closeout, int $after, bool $noLongerAvailable, bool $backInStock): void
    {
        $ids = new IdsCollection();

        $context = Context::createDefaultContext();

        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(10)
            ->stock($stock)
            ->closeout($closeout)
            ->build();

        $this->blogRepository->create([$blog], $context);

        $this->collectAvailabilityEvents();

        $this->blogRepository->update([['id' => $blog['id'], 'stock' => $after]], $context);

        $this->assertAvailabilityEvents($ids->get('p1'), $noLongerAvailable, $backInStock);
    }

    public function testStockAfterOrderBlog(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);

        $this->orderBlog($id, 1);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);
    }

    public function testStockAfterCancel(): void
    {
        $context = Context::createDefaultContext();
        $initialStock = 12;
        $orderQuantity = 8;

        $blogId = $this->createBlog([
            'stock' => $initialStock,
        ]);
        $orderId = $this->orderBlog($blogId, $orderQuantity);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock($initialStock - $orderQuantity, $blog);

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock($initialStock, $blog);
    }

    public function testStockNotReduceDuplicatedWhenReOpenOrder(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(5, $blog);
        static::assertSame(0, $blog->getSales());

        $orderId = $this->orderBlog($id, 1);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);
        static::assertSame(1, $blog->getSales());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');
        $this->transitionOrder($orderId, 'reopen');
        $this->transitionOrder($orderId, 'process');

        $id2 = $this->createBlog();
        $this->orderBlog($id2, 1);

        $this->transitionOrder($orderId, 'complete');

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);
        static::assertSame(1, $blog->getSales());
    }

    public function testUpdateStockAndSalesWithDifferentOrderVersions(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(5, $blog);
        static::assertSame(0, $blog->getSales());

        $orderId = $this->orderBlog($id, 1);

        static::getContainer()->get('order.repository')
            ->createVersion($orderId, $context);

        static::getContainer()->get('order.repository')
            ->createVersion($orderId, $context);

        $count = (int) static::getContainer()
            ->get(Connection::class)
            ->fetchOne('SELECT COUNT(id) FROM `order` WHERE id = :id', ['id' => Uuid::fromHexToBytes($orderId)]);

        static::assertSame(3, $count);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);
        static::assertSame(1, $blog->getSales());

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);
        static::assertSame(1, $blog->getSales());
    }

    public function testBlogGoesOutOfStock(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(5, $blog);

        $orderId = $this->orderBlog($id, 5);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);
    }

    public function testSwitchLineItem(): void
    {
        $id = $this->createBlog();
        $id2 = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(5, $blog);

        $orderId = $this->orderBlog($id, 5);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);

        $lineItemRepository = static::getContainer()->get('order_line_item.repository');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('referencedId', $id));
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItem = $lineItemRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $lineItem);

        $update = [
            ['id' => $lineItem->getId(), 'referencedId' => $id2, 'blogId' => $id2, 'payload' => ['blogNumber' => $id2]],
        ];

        $this->lineItemRepository->update($update, $context);

        /** @var BlogCollection $blogs */
        $blogs = $this->blogRepository->search(new Criteria([$id, $id2]), $context)->getEntities();

        $blog = $blogs->get($id);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);

        $blog = $blogs->get($id2);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);
    }

    public function testStockIsUpdatedIfOrderLineItemIsDeleted(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 5,
        ]);
        $orderId = $this->orderBlog($blogId, 5);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertFalse($blog->getAvailable());
        $this->assertStock(0, $blog);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->lineItemRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->lineItemRepository->delete([
            [
                'id' => $orderLineItem->getId(),
            ],
        ], $context);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);
    }

    public function testAvailableStockIsUpdatedIfBlogOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $originalBlogId = $this->createBlog([
            'stock' => 5,
        ]);
        $newBlogId = $this->createBlog([
            'stock' => 5,
        ]);
        $orderId = $this->orderBlog($originalBlogId, 1);

        $originalBlog = $this->blogRepository->search(new Criteria([$originalBlogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $originalBlog);

        $newBlog = $this->blogRepository->search(new Criteria([$newBlogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $newBlog);
        $this->assertStock(4, $originalBlog);
        $this->assertStock(5, $newBlog);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->lineItemRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->lineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'referencedId' => $newBlog->getId(),
                'blogId' => $newBlog->getId(),
                'payload' => [
                    'blogNumber' => $newBlog->getBlogNumber(),
                ],
            ],
        ], $context);

        $newBlog = $this->blogRepository->search(new Criteria([$newBlogId]), $context)->getEntities()->first();
        $originalBlog = $this->blogRepository->search(new Criteria([$originalBlogId]), $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $newBlog);
        static::assertInstanceOf(BlogEntity::class, $originalBlog);
        $this->assertStock(5, $originalBlog);
        $this->assertStock(4, $newBlog);
    }

    public function testSalesIsUpdatedIfBlogOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $originalBlogId = $this->createBlog([
            'stock' => 5,
        ]);
        $newBlogId = $this->createBlog([
            'stock' => 5,
        ]);

        $orderId = $this->orderBlog($originalBlogId, 1);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $originalBlog = $this->blogRepository->search(new Criteria([$originalBlogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $originalBlog);

        $newBlog = $this->blogRepository->search(new Criteria([$newBlogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $newBlog);

        static::assertSame(1, $originalBlog->getSales());
        static::assertSame(0, $newBlog->getSales());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->lineItemRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->lineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'referencedId' => $newBlog->getId(),
                'blogId' => $newBlog->getId(),
                'payload' => [
                    'blogNumber' => $newBlog->getBlogNumber(),
                ],
            ],
        ], $context);

        $newBlog = $this->blogRepository->search(new Criteria([$newBlogId]), $context)->getEntities()->first();
        $originalBlog = $this->blogRepository->search(new Criteria([$originalBlogId]), $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $newBlog);
        static::assertInstanceOf(BlogEntity::class, $originalBlog);
        static::assertSame(0, $originalBlog->getSales());
        static::assertSame(1, $newBlog->getSales());
    }

    public function testStockIsUpdatedIfQuantityOfOrderLineItemIsChanged(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 5,
        ]);
        $orderId = $this->orderBlog($blogId, 1);
        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(4, $blog);
        static::assertSame(1, $blog->getSales());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $orderLineItem = $this->lineItemRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(OrderLineItemEntity::class, $orderLineItem);

        $this->lineItemRepository->update([
            [
                'id' => $orderLineItem->getId(),
                'quantity' => 2,
            ],
        ], $context);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        // only not completed orders are considered by the stock indexer
        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(3, $blog);
        static::assertSame(2, $blog->getSales());
    }

    public function testOrderCanceled(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 5,
        ]);
        $orderId = $this->orderBlog($blogId, 1);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(4, $blog);

        $this->transitionOrder($orderId, 'cancel');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(5, $blog);

        $this->transitionOrder($orderId, 'reopen');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        $this->assertStock(4, $blog);
    }

    public function testSalesIsUpdatedWhenOrderCancelled(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 5,
        ]);

        $orderId = $this->orderBlog($blogId, 1);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame(1, $blog->getSales());

        $this->transitionOrder($orderId, 'reopen');
        $this->transitionOrder($orderId, 'cancel');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame(0, $blog->getSales());
    }

    public function testSalesIsUpdatedWhenOrderReopened(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 5,
        ]);

        $orderId = $this->orderBlog($blogId, 1);

        $this->transitionOrder($orderId, 'process');
        $this->transitionOrder($orderId, 'complete');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame(1, $blog->getSales());

        $this->transitionOrder($orderId, 'reopen');

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame(1, $blog->getSales());
    }

    public function testDeleteOrderedBlog(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 5,
        ]);

        $this->orderBlog($blogId, 1);

        $this->blogRepository->delete([
            ['id' => $blogId],
        ], $context);
    }

    public function testDeleteOrderIncreasesStock(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);

        $orderId = $this->orderBlog($id, 1);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);

        $this->orderRepository->delete([['id' => $orderId]], $context);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);
    }

    public function testDeleteCancelledOrderDoesNotIncreasesStock(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);

        $orderId = $this->orderBlog($id, 1);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(4, $blog);

        $this->transitionOrder($orderId, StateMachineTransitionActions::ACTION_CANCEL);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);

        $this->orderRepository->delete([['id' => $orderId]], $context);

        $blog = $this->blogRepository->search(new Criteria([$id]), $context)->getEntities()->get($id);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getAvailable());
        $this->assertStock(5, $blog);
    }

    public function testStockAlterationWithClearanceSaleBlog(): void
    {
        $context = Context::createDefaultContext();

        $blogId = $this->createBlog([
            'stock' => 1,
            'isCloseout' => true,
        ]);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->getIsCloseout());
        static::assertTrue($blog->getAvailable());

        $initialUpdatedAt = $blog->getUpdatedAt();
        static::assertNotNull($initialUpdatedAt);

        // Wait to ensure timestamp difference
        sleep(1);

        $this->orderBlog($blogId, 1);

        $blogAfterOrder = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blogAfterOrder);

        $updatedAtAfterOrder = $blogAfterOrder->getUpdatedAt();
        static::assertNotNull($updatedAtAfterOrder);

        static::assertGreaterThan(
            $initialUpdatedAt->getTimestamp(),
            $updatedAtAfterOrder->getTimestamp(),
            'Blog updated_at should be updated when clearance sale stock is altered'
        );

        $this->assertStock(0, $blogAfterOrder);
        static::assertFalse($blogAfterOrder->getAvailable());
    }

    private function assertStock(int $expectedStock, BlogEntity $blog): void
    {
        static::assertSame($expectedStock, $blog->getAvailableStock());
        static::assertSame($expectedStock, $blog->getStock());
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'contena',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => TestDefaults::CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'name' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('user.repository')
            ->upsert([$user], Context::createDefaultContext());

        return $userId;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createBlog(array $config = []): string
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $blog = array_replace_recursive($blog, $config);

        $this->blogRepository->create([$blog], Context::createDefaultContext());
        $this->addTaxDataToChannel($this->context, $blog['tax']);

        return $id;
    }

    private function orderBlog(string $id, int $quantity): string
    {
        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());
        $lineItem = $factory->create(['id' => $id, 'referencedId' => $id, 'quantity' => $quantity], $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        $cart = $this->cartService->add($cart, $lineItem, $this->context);

        $item = $cart->get($id);
        static::assertInstanceOf(LineItem::class, $item);
        static::assertSame($quantity, $item->getQuantity());

        return $this->cartService->order($cart, $this->context, new RequestDataBag());
    }

    private function transitionOrder(string $orderId, string $transition): void
    {
        $registry = static::getContainer()->get(StateMachineRegistry::class);
        $transitionObject = new Transition('order', $orderId, $transition, 'stateId');

        $registry->transition($transitionObject, Context::createDefaultContext());
    }

    private function collectAvailabilityEvents(): void
    {
        $hooks = EventHookDispatcher::fromContainer(static::getContainer());

        $hooks->on(BlogNoLongerAvailableEvent::class, function (BlogNoLongerAvailableEvent $event): void {
            $this->noLongerAvailableIds = [...$this->noLongerAvailableIds, ...$event->getIds()];
        });

        $hooks->on(BlogBackInStockEvent::class, function (BlogBackInStockEvent $event): void {
            $this->backInStockIds = [...$this->backInStockIds, ...$event->getIds()];
        });
    }

    private function assertAvailabilityEvents(string $blogId, bool $noLongerAvailable, bool $backInStock): void
    {
        static::assertSame($noLongerAvailable ? [$blogId] : [], $this->noLongerAvailableIds);
        static::assertSame($backInStock ? [$blogId] : [], $this->backInStockIds);
    }
}

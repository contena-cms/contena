<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Error\GenericCartError;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\LineItemFactoryRegistry;
use Contena\Core\Checkout\Cart\Channel\CartReorderRoute;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Contena\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\AbstractBlogListRoute;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Controller\CartLineItemController;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(CartLineItemController::class)]
class CartLineItemControllerTest extends TestCase
{
    private CartLineItemController $controller;

    private LineItemFactoryRegistry&Stub $lineItemRegistryMock;

    private CartService&Stub $cartService;

    private ContainerInterface&Stub $container;

    private AbstractBlogListRoute&Stub $blogListRouteMock;

    private BlogLineItemFactory&Stub $blogLineItemFactoryMock;

    protected function setUp(): void
    {
        $this->lineItemRegistryMock = static::createStub(LineItemFactoryRegistry::class);
        $this->cartService = static::createStub(CartService::class);
        $this->blogListRouteMock = static::createStub(AbstractBlogListRoute::class);
        $this->blogLineItemFactoryMock = static::createStub(BlogLineItemFactory::class);

        $this->controller = $this->getController();

        $this->container = static::createStub(ContainerInterface::class);

        $this->controller->setContainer($this->container);
    }

    public function testAddLineItemsCallsLineItemWithPayload(): void
    {
        $blogId = Uuid::randomHex();
        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
            'payload' => '{"some": "value"}',
        ];

        $expectedLineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
            'payload' => ['some' => 'value'],
        ];

        $request = new Request([], ['lineItems' => [$blogId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $expectedLineItem = new LineItem($blogId, 'blog');

        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemRegistry->expects($this->once())
            ->method('create')
            ->with($expectedLineItemData, static::createStub(ChannelContext::class))
            ->willReturn($expectedLineItem);

        $this->translatorCallback();

        $controller = $this->getController(lineItemRegistry: $lineItemRegistry);
        $controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsCallsLineItemWithNestedArrayPayload(): void
    {
        $blogId = Uuid::randomHex();
        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
            'payload' => ['some' => 'value', 'nested' => ['key' => 'data']],
        ];

        $expectedLineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
            'payload' => ['some' => 'value', 'nested' => ['key' => 'data']],
        ];

        $request = new Request([], ['lineItems' => [$blogId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $expectedLineItem = new LineItem($blogId, 'blog');

        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemRegistry->expects($this->once())
            ->method('create')
            ->with($expectedLineItemData, static::createStub(ChannelContext::class))
            ->willReturn($expectedLineItem);

        $this->translatorCallback();

        $controller = $this->getController(lineItemRegistry: $lineItemRegistry);
        $controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsCallsLineItemSetDefaultValues(): void
    {
        $blogId = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'priceDefinition' => [
                'quantity' => 5,
                'isCalculated' => 1,
            ],
        ];
        $lineItemData2 = [
            'id' => $blogId2,
            'referencedId' => $blogId2,
            'type' => 'blog',
        ];

        $expectedLineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => true,
            'removable' => true,
            'quantity' => 1,
            'priceDefinition' => [
                'quantity' => 5,
                'isCalculated' => 1,
            ],
        ];

        $expectedLineItemData2 = [
            'id' => $blogId2,
            'referencedId' => $blogId2,
            'type' => 'blog',
            'stackable' => true,
            'removable' => true,
            'quantity' => 1,
        ];

        $request = new Request(
            [],
            [
                'lineItems' => [
                    $blogId => $lineItemData,
                    $blogId2 => $lineItemData2,
                ],
            ]
        );

        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $matcher = $this->exactly(2);
        $lineItemRegistry->expects($matcher)->method('create')
            ->willReturnCallback(
                static function (array $lineItemDataPar, ChannelContext $contextPar) use (
                    $matcher,
                    $expectedLineItemData,
                    $expectedLineItemData2
                ) {
                    match ($matcher->numberOfInvocations()) {
                        default => static::fail('to many calls of create'),
                        2 => static::assertEquals($expectedLineItemData2, $lineItemDataPar),
                        1 => static::assertEquals($expectedLineItemData, $lineItemDataPar),
                    };

                    return new LineItem($lineItemDataPar['id'], 'blog');
                }
            );

        $this->translatorCallback();

        $controller = $this->getController(lineItemRegistry: $lineItemRegistry);
        $controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsCallsLineItemWithTooBigPayload(): void
    {
        $blogId = Uuid::randomHex();
        $bigVal = '';

        for ($x = 0; $x < 9999; ++$x) {
            $bigVal .= 'dsadasdasdasfweat34wt4etgea';
        }

        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
            'payload' => $bigVal,
        ];

        $request = new Request([], ['lineItems' => [$blogId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $this->controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);

        static::assertCount(1, $session->getFlashBag()->all());
    }

    public function testAddLineItemsCallsLineItemFactory(): void
    {
        $blogId = Uuid::randomHex();
        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'blog',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
        ];

        $request = new Request([], ['lineItems' => [$blogId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $expectedLineItem = new LineItem($blogId, 'blog');

        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemRegistry->expects($this->once())
            ->method('create')
            ->with($lineItemData, static::createStub(ChannelContext::class))
            ->willReturn($expectedLineItem);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('add')
            ->with($cart, [$expectedLineItem], $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService, lineItemRegistry: $lineItemRegistry);
        $controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsCartExceptionWillBeThrown(): void
    {
        $blogId = Uuid::randomHex();
        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'nonexistenttype',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
        ];

        $request = new Request([], ['lineItems' => [$blogId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $exception = CartException::invalidPriceDefinition();
        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemRegistry->expects($this->once())
            ->method('create')
            ->with($lineItemData, static::createStub(ChannelContext::class))
            ->willThrowException($exception);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('add');

        $this->expectExceptionObject($exception);
        $controller = $this->getController(cartService: $cartService, lineItemRegistry: $lineItemRegistry);
        $controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddLineItemsCartExceptionWillBeThrownQuantity(): void
    {
        $blogId = Uuid::randomHex();
        $lineItemData = [
            'id' => $blogId,
            'referencedId' => $blogId,
            'type' => 'nonexistenttype',
            'stackable' => 1,
            'removable' => 1,
            'quantity' => 1,
        ];

        $request = new Request([], ['lineItems' => [$blogId => $lineItemData]]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $exception = CartException::invalidQuantity(1);
        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemRegistry->expects($this->once())
            ->method('create')
            ->with($lineItemData, static::createStub(ChannelContext::class))
            ->willThrowException($exception);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('add');

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService, lineItemRegistry: $lineItemRegistry);
        $controller->addLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testAddByBlogNumber(): void
    {
        $blogNumber = Uuid::randomHex();
        $id = Uuid::randomHex();
        $request = new Request([], ['number' => $blogNumber]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier($id);
        $blog->setId($id);
        $item = new LineItem($id, PromotionProcessor::LINE_ITEM_TYPE);

        $cart->add($item);
        $blogListRoute = $this->createMock(AbstractBlogListRoute::class);
        $blogListRoute->expects($this->once())
            ->method('load')
            ->willReturn(
                new BlogListResponse(
                    new EntitySearchResult(
                        BlogDefinition::ENTITY_NAME,
                        1,
                        new BlogCollection([$blog]),
                        null,
                        new Criteria(),
                        Context::createDefaultContext()
                    )
                )
            );

        $blogLineItemFactory = $this->createMock(BlogLineItemFactory::class);
        $blogLineItemFactory->expects($this->once())->method('create')->willReturn($item);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('getCart')->willReturn($cart);

        $cartService->expects($this->once())
            ->method('add')
            ->with($cart, $item, $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(
            cartService: $cartService,
            blogLineItemFactory: $blogLineItemFactory,
            blogListRoute: $blogListRoute
        );
        $controller->addBlogByNumber($request, $context);
    }

    public function testAddByBlogNumberNotFound(): void
    {
        $blogNumber = Uuid::randomHex();
        $id = Uuid::randomHex();
        $request = new Request([], ['number' => $blogNumber]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier($id);
        $blog->setId($id);
        $item = new LineItem($id, PromotionProcessor::LINE_ITEM_TYPE);

        $cart->add($item);
        $blogListRoute = $this->createMock(AbstractBlogListRoute::class);
        $blogListRoute->expects($this->once())
            ->method('load')
            ->willReturn(
                new BlogListResponse(
                    new EntitySearchResult(
                        BlogDefinition::ENTITY_NAME,
                        0,
                        new BlogCollection([]),
                        null,
                        new Criteria(),
                        Context::createDefaultContext()
                    )
                )
            );

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(blogListRoute: $blogListRoute);
        $response = $controller->addBlogByNumber($request, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testAddByBlogNumberTrimsInputBeforeLookup(): void
    {
        $blogNumber = \sprintf(' %s ', Uuid::randomHex());
        $id = Uuid::randomHex();
        $request = new Request([], ['number' => $blogNumber]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier($id);
        $blog->setId($id);
        $item = new LineItem($id, PromotionProcessor::LINE_ITEM_TYPE);

        $cart->add($item);
        $blogListRoute = $this->createMock(AbstractBlogListRoute::class);
        $blogListRoute->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static function (Criteria $criteria) use ($blogNumber): bool {
                    foreach ($criteria->getFilters() as $filter) {
                        if ($filter instanceof EqualsFilter && $filter->getField() === 'blogNumber' && $filter->getValue() === $blogNumber) {
                            return true;
                        }
                    }

                    return false;
                }),
                $context
            )
            ->willReturn(
                new BlogListResponse(
                    new EntitySearchResult(
                        BlogDefinition::ENTITY_NAME,
                        1,
                        new BlogCollection([$blog]),
                        null,
                        new Criteria(),
                        Context::createDefaultContext()
                    )
                )
            );

        $blogLineItemFactory = $this->createMock(BlogLineItemFactory::class);
        $blogLineItemFactory->expects($this->once())->method('create')->willReturn($item);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('getCart')->willReturn($cart);

        $cartService->expects($this->once())
            ->method('add')
            ->with($cart, $item, $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(
            cartService: $cartService,
            blogLineItemFactory: $blogLineItemFactory,
            blogListRoute: $blogListRoute
        );
        $controller->addBlogByNumber($request, $context);
    }

    public function testAddByBlogNumberWithBlankInput(): void
    {
        $request = new Request([], ['number' => '   ']);
        $context = static::createStub(ChannelContext::class);

        $blogListRoute = $this->createMock(AbstractBlogListRoute::class);
        $blogListRoute->expects($this->never())->method('load');
        $blogLineItemFactory = $this->createMock(BlogLineItemFactory::class);
        $blogLineItemFactory->expects($this->never())->method('create');
        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('getCart');
        $cartService->expects($this->never())->method('add');

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(
            cartService: $cartService,
            blogLineItemFactory: $blogLineItemFactory,
            blogListRoute: $blogListRoute
        );
        $response = $controller->addBlogByNumber($request, $context);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testAddPromotion(): void
    {
        $code = Uuid::randomHex();

        $request = new Request([], ['code' => $code]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $uniqueKey = PromotionItemBuilder::PLACEHOLDER_PREFIX . $code;
        $item = new LineItem($uniqueKey, PromotionProcessor::LINE_ITEM_TYPE);
        $item->setLabel($code);
        $cart->addErrors(new PromotionCartAddedInformationError($item));

        $lineItemRegistry = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemRegistry->expects($this->once())
            ->method('create')
            ->with(['type' => LineItem::PROMOTION_LINE_ITEM_TYPE, 'referencedId' => $code], $context)
            ->willReturn($item);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('add')
            ->with($cart, $item, $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService, lineItemRegistry: $lineItemRegistry);
        $controller->addPromotion($cart, $request, $context);
    }

    public function testAddPromotionOtherExceptions(): void
    {
        $code = Uuid::randomHex();

        $request = new Request([], ['code' => $code]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);
        $uniqueKey = PromotionItemBuilder::PLACEHOLDER_PREFIX . $code;
        $item = new LineItem($uniqueKey, PromotionProcessor::LINE_ITEM_TYPE);
        $item->setLabel($code);
        $cart->addErrors(new GenericCartError('d', 's', [], 0, false, true, false));

        $this->lineItemRegistryMock->method('create')->willReturn($item);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('add')
            ->with($cart, $item, $context)
            ->willReturn($cart);

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(cartService: $cartService);
        $controller->addPromotion($cart, $request, $context);
    }

    public function testAddPromotionNoCode(): void
    {
        $code = '';

        $request = new Request([], ['code' => $code]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())
            ->method('add');

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService);
        $controller->addPromotion($cart, $request, $context);
    }

    public function testChangeQuantity(): void
    {
        $id = Uuid::randomHex();

        $request = new Request([], ['quantity' => 3]);
        $cart = new Cart(Uuid::randomHex());
        $cart->addLineItems(new LineItemCollection([new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE)]));
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('changeQuantity')
            ->with($cart, $id, 3, $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService);
        $controller->changeQuantity($cart, $id, $request, $context);
    }

    public function testChangeQuantityNoParam(): void
    {
        $id = Uuid::randomHex();

        $request = new Request([]);
        $cart = new Cart(Uuid::randomHex());
        $cart->addLineItems(new LineItemCollection([new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE)]));
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())
            ->method('changeQuantity');

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(cartService: $cartService);
        $controller->changeQuantity($cart, $id, $request, $context);

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testChangeQuantityNoLineItem(): void
    {
        $id = Uuid::randomHex();

        $request = new Request(['quantity' => 3]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())
            ->method('changeQuantity');

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(cartService: $cartService);
        $controller->changeQuantity($cart, $id, $request, $context);

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testDeleteLineItem(): void
    {
        $id = Uuid::randomHex();

        $request = new Request([]);
        $cart = new Cart(Uuid::randomHex());
        $cart->addLineItems(new LineItemCollection([new LineItem($id, LineItem::BLOG_LINE_ITEM_TYPE)]));
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('remove')
            ->with($cart, $id, $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService);
        $controller->deleteLineItem($cart, $id, $request, $context);
    }

    public function testDeleteLineItemNotInCart(): void
    {
        $id = Uuid::randomHex();

        $request = new Request([]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())
            ->method('remove');

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(cartService: $cartService);
        $controller->deleteLineItem($cart, $id, $request, $context);

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testDeleteLineItems(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $ids = [$id1, $id2];

        $request = new Request([], ['ids' => $ids]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('removeItems')
            ->with($cart, $ids, $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService);
        $controller->deleteLineItems($cart, $request, $context);
    }

    public function testDeleteLineItemsMissingIdsParameter(): void
    {
        $request = new Request();
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('remove');

        $session = new Session(new MockArraySessionStorage());
        $this->translatorCallback($session);

        $controller = $this->getController(cartService: $cartService);
        $controller->deleteLineItems($cart, $request, $context);

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testDeleteLineItemsWrongParameter(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = 123;
        $ids = [$id1, $id2];

        $request = new Request([], ['ids' => $ids]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('remove');

        $stack = static::createStub(RequestStack::class);
        $session = new Session(new MockArraySessionStorage());
        $stack->method('getSession')->willReturn($session);
        $this->container->method('get')
            ->willReturnCallback(function ($id) use ($stack) {
                if ($id === 'translator') {
                    return $this->createStub(TranslatorInterface::class);
                }

                if ($id === 'request_stack') {
                    return $stack;
                }

                return null;
            });

        $controller = $this->getController(cartService: $cartService);
        $controller->deleteLineItems($cart, $request, $context);

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    public function testDeleteCart(): void
    {
        $request = new Request([], ['all' => true]);
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('deleteCart')
            ->with($context);

        $controller = $this->getController(cartService: $cartService);
        $controller->deleteCart($request, $context);
    }

    public function testUpdateLineItems(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        // Raw request payload: browsers submit everything as strings.
        $lineItems = [
            ['id' => $id1, 'quantity' => '5', 'stackable' => '1', 'priceDefinition' => ['quantity' => '5', 'isCalculated' => '1']],
            ['id' => $id2, 'removable' => '0'],
        ];

        $request = new Request([], ['lineItems' => $lineItems]);
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        // The controller must normalize the raw strings to int/bool before handing them to update().
        // identicalTo() enforces the types strictly; the default with() uses == and would pass uncast.
        $expectedItems = [
            ['id' => $id1, 'quantity' => 5, 'stackable' => true, 'priceDefinition' => ['quantity' => 5, 'isCalculated' => 1]],
            ['id' => $id2, 'removable' => false],
        ];

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->once())
            ->method('update')
            ->with($cart, static::identicalTo($expectedItems), $context)
            ->willReturn($cart);

        $this->translatorCallback();

        $controller = $this->getController(cartService: $cartService);
        $controller->updateLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);
    }

    public function testDeleteLineItemsMissingParameter(): void
    {
        $request = new Request();
        $cart = new Cart(Uuid::randomHex());
        $context = static::createStub(ChannelContext::class);

        $cartService = $this->createMock(CartService::class);
        $cartService->expects($this->never())->method('update');

        $stack = static::createStub(RequestStack::class);
        $session = new Session(new MockArraySessionStorage());
        $stack->method('getSession')->willReturn($session);
        $this->container->method('get')
            ->willReturnCallback(function ($id) use ($stack) {
                if ($id === 'translator') {
                    return $this->createStub(TranslatorInterface::class);
                }

                if ($id === 'request_stack') {
                    return $stack;
                }

                return null;
            });

        $controller = $this->getController(cartService: $cartService);
        $controller->updateLineItems($cart, new RequestDataBag($request->request->all()), $request, $context);

        static::assertArrayHasKey('danger', $session->getFlashBag()->peekAll());
    }

    private function getController(
        ?CartService $cartService = null,
        ?BlogLineItemFactory $blogLineItemFactory = null,
        ?AbstractBlogListRoute $blogListRoute = null,
        ?LineItemFactoryRegistry $lineItemRegistry = null,
        ?CartReorderRoute $cartReorderRoute = null
    ): CartLineItemController {
        $controller = new CartLineItemController(
            $cartService ?? $this->cartService,
            $blogLineItemFactory ?? $this->blogLineItemFactoryMock,
            static::createStub(HtmlSanitizer::class),
            $blogListRoute ?? $this->blogListRouteMock,
            $lineItemRegistry ?? $this->lineItemRegistryMock,
            $cartReorderRoute ?? static::createStub(CartReorderRoute::class),
        );

        if (isset($this->container)) {
            $controller->setContainer($this->container);
        }

        return $controller;
    }

    private function translatorCallback(?Session $session = null): void
    {
        if (!$session instanceof Session) {
            $session = new Session(new MockArraySessionStorage());
        }
        $stack = static::createStub(RequestStack::class);
        $stack->method('getSession')->willReturn($session);
        $this->container->method('get')
            ->willReturnCallback(function ($id) use ($stack) {
                if ($id === 'translator') {
                    return $this->createStub(TranslatorInterface::class);
                }

                if ($id === 'request_stack') {
                    return $stack;
                }

                return null;
            });
    }
}

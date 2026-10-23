<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Channel\Context;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\AbstractCartPersister;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Contena\Core\Checkout\Cart\Event\CartMergedEvent;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\CartRestorer;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Event\ChannelContextRestoredEvent;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Builder\Promotion\PromotionFixtureBuilder;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
class CartRestorerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;

    private Connection $connection;

    private CartRestorer $cartRestorer;

    private CartService $cartService;

    private AbstractCartPersister $cartPersister;

    /**
     * @var array<string, Event>
     */
    private array $events;

    private \Closure $callbackFn;

    private EventDispatcherInterface $eventDispatcher;

    private ChannelContextPersister $contextPersister;

    private string $userId;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->cartPersister = static::getContainer()->get(CartPersister::class);

        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->events = [];

        $this->callbackFn = function (Event $event): void {
            $this->events[$event::class] = $event;
        };

        $this->contextPersister = static::getContainer()->get(ChannelContextPersister::class);
        /** @var AbstractChannelContextFactory $contextFactory */
        $contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $cartCalculator = static::getContainer()->get(CartCalculator::class);
        $requestStack = static::getContainer()->get(RequestStack::class);

        $this->userId = $this->createUser()->getId();

        $this->cartRestorer = new CartRestorer(
            $contextFactory,
            $this->contextPersister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $requestStack
        );
    }

    /**
     * Check if a cart is restored correctly by its token. The cart should be restored with the same line items and
     * keep its token.
     *
     * @throws \JsonException
     */
    public function testRestoreByToken(): void
    {
        $currentContextToken = Uuid::randomHex();
        $currentContext = $this->createChannelContext($currentContextToken);

        $guestToken = Uuid::randomHex();
        $guestContext = $this->createChannelContext($guestToken);

        $this->contextPersister->save($currentContextToken, [], $currentContext->getChannelId());
        $this->contextPersister->save($guestToken, [], $guestContext->getChannelId());

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextRestoredEvent::class, $this->callbackFn);

        $blogLineItem1 = $this->createLineItem($currentContext, 2);
        $blogLineItem2 = $this->createLineItem($currentContext, 3);

        $this->createAndSaveUnmodifiedCart($currentContext, $blogLineItem1, $blogLineItem2);

        $restoredContext = $this->cartRestorer->restoreByToken($currentContextToken, $this->userId, $guestContext);

        static::assertSame($currentContext->getToken(), $restoredContext->getToken());

        static::assertArrayHasKey(ChannelContextRestoredEvent::class, $this->events);
        $channelRestoredEvent = $this->events[ChannelContextRestoredEvent::class];
        static::assertInstanceOf(ChannelContextRestoredEvent::class, $channelRestoredEvent);

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItem1 = $restoredCart->getLineItems()->get($blogLineItem1->getId());
        $restoredLineItem2 = $restoredCart->getLineItems()->get($blogLineItem2->getId());

        static::assertInstanceOf(LineItem::class, $restoredLineItem1);
        static::assertInstanceOf(LineItem::class, $restoredLineItem2);
        static::assertFalse($restoredCart->isModified());

        static::assertSame($blogLineItem1->getQuantity(), $restoredLineItem1->getQuantity());
        static::assertSame(2, $restoredLineItem1->getQuantity());
        static::assertSame($blogLineItem2->getQuantity(), $restoredLineItem2->getQuantity());
        static::assertSame(3, $restoredLineItem2->getQuantity());
    }

    public function testRestoreByTokenIsMergedWithGuestCart(): void
    {
        // Create Guest cart
        $guestBlogQuantity = 3;
        ['context' => $guestContext, 'cart' => $guestCart] = $this->createContextAndFilledCart(1, $guestBlogQuantity);
        $guestBlogLineItem1 = $guestCart->getLineItems()->getAt(0);
        $guestBlogLineItem2 = $guestCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $guestBlogLineItem1);
        static::assertInstanceOf(LineItem::class, $guestBlogLineItem2);

        // Create Saved User cart
        $userLineItemQuantity = 4;
        ['context' => $userContext, 'cart' => $userCart] = $this->createContextAndFilledCart($userLineItemQuantity, 3, true);
        $userToken = $userContext->getToken();
        $userLineItem1 = $userCart->getLineItems()->getAt(0);
        $userLineItem2 = $userCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $userLineItem1);
        static::assertInstanceOf(LineItem::class, $userLineItem2);

        $combinedLineItemsCount = $guestCart->getLineItems()->count() + $userCart->getLineItems()->count();

        $restoredContext = $this->cartRestorer->restoreByToken(
            $userToken,
            $this->userId,
            $guestContext
        );

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItems = $restoredCart->getLineItems();

        static::assertFalse($restoredCart->isModified());
        static::assertCount($combinedLineItemsCount, $restoredLineItems);

        static::assertIsString($guestBlogLineItem1->getReferencedId());
        static::assertIsString($guestBlogLineItem2->getReferencedId());
        static::assertIsString($userLineItem1->getReferencedId());
        static::assertIsString($userLineItem2->getReferencedId());

        $restoredGuestBlogLineItem1 = $restoredLineItems->get($guestBlogLineItem1->getReferencedId());
        static::assertNotNull($restoredGuestBlogLineItem1);
        static::assertSame(1, $restoredGuestBlogLineItem1->getQuantity());

        $restoredGuestBlogLineItem2 = $restoredLineItems->get($guestBlogLineItem2->getReferencedId());
        static::assertNotNull($restoredGuestBlogLineItem2);
        static::assertSame($guestBlogQuantity, $restoredGuestBlogLineItem2->getQuantity());

        $restoredUserLineItem1 = $restoredLineItems->get($userLineItem1->getReferencedId());
        static::assertNotNull($restoredUserLineItem1);
        static::assertSame($userLineItemQuantity, $restoredUserLineItem1->getQuantity());

        $restoredUserLineItem2 = $restoredLineItems->get($userLineItem2->getReferencedId());
        static::assertNotNull($restoredUserLineItem2);
        static::assertSame(3, $restoredUserLineItem2->getQuantity());
    }

    public function testRestoreByTokenWithNotExistingToken(): void
    {
        $formerContext = $this->createChannelContext('formerToken');
        $currentContext = $this->createChannelContext('currentToken');

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->cartRestorer->restoreByToken($formerContext->getToken(), $this->userId, $currentContext);

        static::assertSame($formerContext->getToken(), $restoredContext->getToken());

        static::assertArrayNotHasKey(ChannelContextRestoredEvent::class, $this->events);
    }

    public function testRestoreByTokenIsReplacedWithNoExistingToken(): void
    {
        // Create Guest cart
        ['context' => $guestContext, 'cart' => $guestCart] = $this->createContextAndFilledCart(3, 4);
        $guestBlogLineItem1 = $guestCart->getLineItems()->getAt(0);
        $guestBlogLineItem2 = $guestCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $guestBlogLineItem1);
        static::assertInstanceOf(LineItem::class, $guestBlogLineItem2);

        $currentContext = $this->createChannelContext('currentToken');

        $restoredContext = $this->cartRestorer->restoreByToken(
            $currentContext->getToken(),
            $this->userId,
            $guestContext
        );

        static::assertSame($currentContext->getToken(), $restoredContext->getToken());

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItems = $restoredCart->getLineItems();

        static::assertFalse($restoredCart->isModified());
        static::assertSame(7, $restoredLineItems->getTotalQuantity());

        static::assertIsString($guestBlogLineItem1->getReferencedId());
        static::assertIsString($guestBlogLineItem2->getReferencedId());

        $restoredGuestBlogLineItem1 = $restoredLineItems->get($guestBlogLineItem1->getReferencedId());
        static::assertNotNull($restoredGuestBlogLineItem1);
        static::assertSame(3, $restoredGuestBlogLineItem1->getQuantity());

        $restoredGuestBlogLineItem2 = $restoredLineItems->get($guestBlogLineItem2->getReferencedId());
        static::assertNotNull($restoredGuestBlogLineItem2);
        static::assertSame(4, $restoredGuestBlogLineItem2->getQuantity());
    }

    public function testRestoreByTokenIsReplacedWhenExpired(): void
    {
        // Create Guest cart
        $guestBlogQuantity = 5;
        ['context' => $guestContext, 'cart' => $guestCart] = $this->createContextAndFilledCart(3, $guestBlogQuantity);
        $guestBlogLineItem1 = $guestCart->getLineItems()->getAt(0);
        $guestBlogLineItem2 = $guestCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $guestBlogLineItem1);
        static::assertInstanceOf(LineItem::class, $guestBlogLineItem2);

        // Create Saved User cart
        $userLineItemQuantity = 2;
        ['context' => $userContext, 'cart' => $userCart] = $this->createContextAndFilledCart($userLineItemQuantity, 3, true);
        $userToken = $userContext->getToken();

        $userLineItem1 = $userCart->getLineItems()->getAt(0);
        $userLineItem2 = $userCart->getLineItems()->getAt(1);
        static::assertInstanceOf(LineItem::class, $userLineItem1);
        static::assertInstanceOf(LineItem::class, $userLineItem2);

        $this->connection->executeStatement(<<<'SQL'
            UPDATE channel_api_context
            SET updated_at = DATE_SUB(updated_at, INTERVAL 7 DAY)
            WHERE token = :token
            SQL
            , ['token' => $userToken]);

        $restoredContext = $this->cartRestorer->restoreByToken(
            $userToken,
            $this->userId,
            $guestContext
        );

        static::assertArrayNotHasKey(ChannelContextRestoredEvent::class, $this->events);

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);
        $restoredLineItems = $restoredCart->getLineItems();

        static::assertFalse($restoredCart->isModified());
        static::assertCount(2, $restoredLineItems);
        static::assertSame(3 + $guestBlogQuantity, $restoredLineItems->getTotalQuantity());

        static::assertIsString($guestBlogLineItem1->getReferencedId());
        static::assertIsString($guestBlogLineItem2->getReferencedId());
        static::assertIsString($userLineItem1->getReferencedId());
        static::assertIsString($userLineItem2->getReferencedId());

        $restoredGuestBlogLineItem1 = $restoredLineItems->get($guestBlogLineItem1->getReferencedId());
        static::assertNotNull($restoredGuestBlogLineItem1);
        static::assertSame(3, $restoredGuestBlogLineItem1->getQuantity());

        $restoredGuestBlogLineItem2 = $restoredLineItems->get($guestBlogLineItem2->getReferencedId());
        static::assertNotNull($restoredGuestBlogLineItem2);
        static::assertSame($guestBlogQuantity, $restoredGuestBlogLineItem2->getQuantity());

        $restoredUserLineItem1 = $restoredLineItems->get($userLineItem1->getReferencedId());
        static::assertNull($restoredUserLineItem1);

        $restoredUserLineItem2 = $restoredLineItems->get($userLineItem2->getReferencedId());
        static::assertNull($restoredUserLineItem2);
    }

    public function testRestore(): void
    {
        $expectedToken = Uuid::randomHex();
        $expectedContext = $this->createChannelContext($expectedToken);

        $currentContext = $this->createChannelContext('currentToken');

        $this->contextPersister->save($expectedContext->getToken(), [], $currentContext->getChannelId(), $this->userId);

        EventHookDispatcher::fromContainer(static::getContainer())->on(ChannelContextRestoredEvent::class, $this->callbackFn);

        $restoredContext = $this->cartRestorer->restore($this->userId, $currentContext);

        static::assertSame($expectedContext->getToken(), $restoredContext->getToken());

        static::assertArrayHasKey(ChannelContextRestoredEvent::class, $this->events);
        $channelRestoredEvent = $this->events[ChannelContextRestoredEvent::class];
        static::assertInstanceOf(ChannelContextRestoredEvent::class, $channelRestoredEvent);
    }

    public function testRestoreWithRevokedTokensCreatesContextWithUser(): void
    {
        $userGroupId = Uuid::randomHex();
        static::getContainer()->get('user_group.repository')->create([
            ['id' => $userGroupId, 'name' => 'special group'],
        ], Context::createDefaultContext());
        static::getContainer()->get('user.repository')->update([
            ['id' => $this->userId, 'groupId' => $userGroupId],
        ], Context::createDefaultContext());

        // The user was logged in before, so a user bound context exists
        $userToken = Uuid::randomHex();
        $userContext = $this->createChannelContext($userToken);
        $this->contextPersister->save($userToken, [], $userContext->getChannelId(), $this->userId);

        // All user tokens get revoked, e.g. by a password change through the administration
        $this->contextPersister->revokeAllUserTokens($this->userId);

        // The frontend session still uses the revoked token, which is anonymous now
        $currentContext = $this->createChannelContext($userToken);

        $restoredContext = $this->cartRestorer->restore($this->userId, $currentContext);

        $user = $restoredContext->getUser();
        static::assertNotNull($user);
        static::assertSame($this->userId, $user->getId());
        static::assertSame($userGroupId, $restoredContext->getUserGroupId());
    }

    public function testGuestContextAndCartAreDeleted(): void
    {
        $currentContextToken = Uuid::randomHex();

        $currentContext = $this->createChannelContext($currentContextToken);

        $this->contextPersister->save($currentContextToken, [], $currentContext->getChannelId(), $this->userId);

        $this->createAndSaveUnmodifiedCart(
            $currentContext,
            $this->createLineItem($currentContext)->setType(LineItem::CUSTOM_LINE_ITEM_TYPE)
        );

        $newContext = $this->cartRestorer->restore($this->userId, $currentContext);

        static::assertTrue($this->cartExists($newContext->getToken()));
        static::assertTrue($this->contextExists($newContext->getToken()));

        static::assertFalse($this->cartExists($currentContextToken));
        static::assertFalse($this->contextExists($currentContextToken));
    }

    public function testCartIsRecalculated(): void
    {
        $userContextToken = Uuid::randomHex();

        $userContext = $this->createChannelContext($userContextToken);

        $this->contextPersister->save($userContextToken, [], $userContext->getChannelId(), $this->userId);

        $blogLineItem = $this->createLineItem($userContext, 1);
        $blogLineItem->setId(Uuid::randomHex());
        $cart = $this->createAndSaveUnmodifiedCart($userContext, $blogLineItem);

        static::assertCount(1, $cart->getLineItems());

        static::getContainer()->get('blog.repository')->delete([[
            'id' => $blogLineItem->getReferencedId(),
        ]], $userContext->getContext());

        $guestContext = $this->createChannelContext('123123');

        $restoreContext = $this->cartRestorer->restore($this->userId, $guestContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        // The deleted blog will be removed from the cart as a result of recalculation
        static::assertCount(0, $restoreCart->getLineItems());
    }

    public function testCartIsMergedAndRecalculatedWithTheSavedOne(): void
    {
        $currentContextToken = Uuid::randomHex();
        $currentContext = $this->createChannelContext($currentContextToken);
        $this->contextPersister->save($currentContextToken, [], $currentContext->getChannelId(), $this->userId);

        $guestBlogQuantity = 5;
        $blogLineItem1 = $this->createLineItem($currentContext, 1);
        $blogLineItem2 = $this->createLineItem($currentContext, $guestBlogQuantity);

        // Create Guest cart
        $this->createAndSaveUnmodifiedCart(
            $currentContext,
            $blogLineItem1,
            $blogLineItem2
        );

        // Create Saved User cart
        $userToken = Uuid::randomHex();
        $userContext = $this->createChannelContext($userToken);
        $this->contextPersister->save($userToken, [], $currentContext->getChannelId(), $this->userId);

        $savedLineItemQuantity = 4;
        $savedLineItem = $this->createLineItem($userContext, $savedLineItemQuantity);
        static::assertIsString($blogLineItem2->getReferencedId());
        $savedLineItem->setId($blogLineItem2->getReferencedId());
        $savedLineItem->setReferencedId($blogLineItem2->getReferencedId());

        $blogLineItem3 = $this->createLineItem($userContext, 3);

        $this->createAndSaveUnmodifiedCart(
            $userContext,
            $savedLineItem,
            $blogLineItem3
        );

        // Delete 1 saved item
        static::getContainer()->get('blog.repository')->delete([[
            'id' => $blogLineItem3->getReferencedId(),
        ]], $userContext->getContext());

        EventHookDispatcher::fromContainer(static::getContainer())->on(CartMergedEvent::class, $this->callbackFn);

        $restoreContext = $this->cartRestorer->restore($this->userId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        static::assertIsString($blogLineItem3->getReferencedId());
        // The deleted blog will be removed from the cart as a result of recalculation
        static::assertNull($restoreCart->getLineItems()->get($blogLineItem3->getReferencedId()));

        static::assertArrayHasKey(CartMergedEvent::class, $this->events);
        $cartMergedEvent = $this->events[CartMergedEvent::class];
        static::assertInstanceOf(CartMergedEvent::class, $cartMergedEvent);

        static::assertCount(1, $cartMergedEvent->getPreviousCart()->getLineItems());
        static::assertSame($cartMergedEvent->getCart()->getToken(), $cartMergedEvent->getPreviousCart()->getToken());

        static::assertIsString($blogLineItem1->getReferencedId());
        static::assertNotNull($p1 = $restoreCart->getLineItems()->get($blogLineItem1->getReferencedId()));
        static::assertSame(1, $p1->getQuantity());
        static::assertNotNull($savedItem = $restoreCart->getLineItems()->get($savedLineItem->getId()));
        static::assertSame($savedLineItemQuantity + $guestBlogQuantity, $savedItem->getQuantity());
    }

    public function testCartMergedEventIsFiredWithUserCart(): void
    {
        $currentContextToken = Uuid::randomHex();
        $currentContext = $this->createChannelContext($currentContextToken);
        $this->contextPersister->save($currentContextToken, [], $currentContext->getChannelId(), $this->userId);

        // Create Guest cart
        $guestBlogQuantity = 5;
        $blogLineItem1 = $this->createLineItem($currentContext, 1);
        $blogLineItem2 = $this->createLineItem($currentContext, $guestBlogQuantity);
        $this->createAndSaveUnmodifiedCart(
            $currentContext,
            $blogLineItem1,
            $blogLineItem2
        );

        // Create Saved User cart
        $userToken = Uuid::randomHex();
        $userContext = $this->createChannelContext($userToken);

        $this->contextPersister->save($userToken, [], $currentContext->getChannelId(), $this->userId);

        $userCart = new Cart($userToken);
        $this->cartPersister->save($userCart, $userContext);

        EventHookDispatcher::fromContainer(static::getContainer())->on(BeforeCartMergeEvent::class, $this->callbackFn);
        EventHookDispatcher::fromContainer(static::getContainer())->on(CartMergedEvent::class, $this->callbackFn);

        $restoreContext = $this->cartRestorer->restore($this->userId, $currentContext);

        $restoreCart = $this->cartService->getCart($restoreContext->getToken(), $restoreContext);

        static::assertFalse($restoreCart->isModified());
        static::assertArrayHasKey(BeforeCartMergeEvent::class, $this->events);
        static::assertArrayHasKey(CartMergedEvent::class, $this->events);

        /** @var CartMergedEvent $event */
        $event = $this->events[CartMergedEvent::class];

        static::assertNotNull($event->getPreviousCart());
        static::assertCount(0, $event->getPreviousCart()->getLineItems());
        static::assertSame($event->getCart()->getToken(), $event->getPreviousCart()->getToken());

        static::assertIsString($blogLineItem1->getReferencedId());
        static::assertNotNull($p1 = $restoreCart->getLineItems()->get($blogLineItem1->getReferencedId()));
        static::assertSame(1, $p1->getQuantity());

        static::assertIsString($blogLineItem2->getReferencedId());
        static::assertNotNull($p2 = $restoreCart->getLineItems()->get($blogLineItem2->getReferencedId()));
        static::assertSame($guestBlogQuantity, $p2->getQuantity());
    }

    public function testPermissionsAreIgnoredOnRestore(): void
    {
        $currentContextToken = Uuid::randomHex();

        $currentContext = $this->createChannelContext($currentContextToken);

        $con = static::getContainer()->get(Connection::class);

        $con->insert('channel_api_context', [
            'token' => Uuid::randomHex(),
            'payload' => \json_encode(['expired' => false, 'userId' => $this->userId, 'permissions' => ['foo']], \JSON_THROW_ON_ERROR),
            'channel_id' => Uuid::fromHexToBytes($currentContext->getChannelId()),
            'user_id' => Uuid::fromHexToBytes($this->userId),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $restoreContext = $this->cartRestorer->restore($this->userId, $currentContext);

        static::assertSame([], $restoreContext->getPermissions());
    }

    public function testMergeCartsWithSetGroupPromotionDoesNotCrash(): void
    {
        $baseContext = Context::createDefaultContext();
        $blogId1 = $this->createBlog($baseContext);
        $blogId2 = $this->createBlog($baseContext);

        $code = 'SET' . Random::getAlphanumericString(5);

        $container = static::getContainer();
        (new PromotionFixtureBuilder(
            Uuid::randomHex(),
            $container->get(ChannelContextFactory::class),
            $container->get('promotion.repository'),
            $container->get('promotion_setgroup.repository'),
            $container->get('promotion_discount.repository')
        ))
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_PERCENTAGE, 10.0, false, null)
            ->buildPromotion();

        $guestToken = Uuid::randomHex();
        $guestContext = $this->createChannelContext($guestToken);
        $this->contextPersister->save($guestToken, [], $guestContext->getChannelId());

        $userToken = Uuid::randomHex();
        $userContext = $this->createChannelContext($userToken);
        $this->contextPersister->save($userToken, [], $userContext->getChannelId(), $this->userId);

        $guestCart = $this->cartService->getCart($guestToken, $guestContext);
        $guestCart = $this->addBlog($blogId1, 1, $guestCart, $this->cartService, $guestContext);
        $guestCart = $this->addBlog($blogId2, 1, $guestCart, $this->cartService, $guestContext);
        $guestCart = $this->addPromotionCode($code, $guestCart, $this->cartService, $guestContext);

        static::assertGreaterThan(0, $guestCart->getLineItems()->count());

        $userCart = $this->cartService->getCart($userToken, $userContext);
        $userCart = $this->addBlog($blogId1, 1, $userCart, $this->cartService, $userContext);
        $userCart = $this->addBlog($blogId2, 1, $userCart, $this->cartService, $userContext);
        $userCart = $this->addPromotionCode($code, $userCart, $this->cartService, $userContext);

        static::assertGreaterThan(0, $userCart->getLineItems()->count());

        $restoredContext = $this->cartRestorer->restore($this->userId, $guestContext);

        $restoredCart = $this->cartService->getCart($restoredContext->getToken(), $restoredContext);

        static::assertFalse($restoredCart->isModified());
        static::assertGreaterThan(0, $restoredCart->getLineItems()->count());
    }

    private function createBlog(Context $context): string
    {
        $blogId = Uuid::randomHex();

        $blogNumber = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'name' => 'Test Blog',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$data], $context);

        return $blogId;
    }

    private function createChannelContext(string $contextToken): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::CHANNEL
        );
    }

    private function cartExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM cart WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    private function contextExists(string $token): bool
    {
        $result = (int) $this->connection->executeQuery(
            'SELECT COUNT(*) FROM channel_api_context WHERE `token` = :token',
            [
                'token' => $token,
            ]
        )->fetchOne();

        return $result > 0;
    }

    private function createUser(): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'foo@bar.de',
            'password' => 'password',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], Context::createDefaultContext());

        $entity = $repo->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $entity);

        return $entity;
    }

    private function createLineItem(
        ChannelContext $context,
        ?int $quantity = null
    ): LineItem {
        $blogId = $this->createBlog($context->getContext());

        $blogLineItem = new LineItem($blogId, LineItem::BLOG_LINE_ITEM_TYPE, $blogId);
        $blogLineItem->setStackable(true);

        if ($quantity !== null) {
            $blogLineItem->setQuantity($quantity);
        }

        return $blogLineItem;
    }

    /**
     * Create a new cart with the provided line items and save it to the database. The cart must contain at least one
     * $lineItems, else it won't be saved and the included assertion will fail.
     */
    private function createAndSaveUnmodifiedCart(ChannelContext $context, LineItem ...$lineItems): Cart
    {
        $cart = new Cart($context->getToken());

        $cart->addLineItems(new LineItemCollection($lineItems));
        $cart->markUnmodified();

        $this->cartPersister->save($cart, $context);

        static::assertTrue($this->cartExists($context->getToken()));
        static::assertTrue($this->contextExists($context->getToken()));

        return $cart;
    }

    /**
     * @return array{context:ChannelContext, cart:Cart}
     */
    private function createContextAndFilledCart(
        ?int $firstLineItemCount = 1,
        ?int $secondLIneItemCount = 3,
        ?bool $withUserId = false,
    ): array {
        $contextToken = Uuid::randomHex();
        $contextUserId = $withUserId ? $this->userId : null;
        $context = $this->createChannelContext($contextToken);
        $this->contextPersister->save(
            $contextToken,
            [],
            $context->getChannelId(),
            $contextUserId
        );

        $blogLineItem1 = $this->createLineItem($context, $firstLineItemCount);
        $blogLineItem2 = $this->createLineItem($context, $secondLIneItemCount);

        $cart = $this->createAndSaveUnmodifiedCart($context, $blogLineItem1, $blogLineItem2);

        return ['context' => $context, 'cart' => $cart];
    }
}

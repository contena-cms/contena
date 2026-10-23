<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cart;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\BlogLineItemFactory;
use Contena\Core\Checkout\Cart\PriceDefinitionFactory;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Exception\BlogLineItemDifferentIdException;
use Contena\Core\Content\Blog\Exception\BlogLineItemInconsistentException;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogLineItemCommandValidatorTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    private CartService $cartService;

    private AbstractChannelContextFactory $contextFactory;

    private ChannelContext $context;

    /**
     * @var EntityRepository<OrderLineItemCollection>
     */
    private EntityRepository $lineItemRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = static::getContainer()->get('blog.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->contextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->lineItemRepository = static::getContainer()->get('order_line_item.repository');
        $this->addCountriesToChannel();

        $this->context = $this->contextFactory->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [
                ChannelContextService::CUSTOMER_ID => $this->createUser(),
            ]
        );
    }

    public function testOrderBlog(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $orderId = $this->orderBlog($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);
        /** @var OrderLineItemEntity $first */
        $first = $lineItems->first();

        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getBlogId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('blogNumber', $first->getPayload());
        static::assertSame(LineItem::BLOG_LINE_ITEM_TYPE, $first->getType());
    }

    public function testUpdateLineItemQuantity(): void
    {
        $id = $this->createBlog();

        $context = Context::createDefaultContext();

        $orderId = $this->orderBlog($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);
        /** @var OrderLineItemEntity $first */
        $first = $lineItems->first();

        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getBlogId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('blogNumber', $first->getPayload());

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'quantity' => 10],
        ], $context);

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();
        $first = $lineItems->first();
        static::assertNotNull($first);
        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getBlogId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('blogNumber', $first->getPayload());
        static::assertSame(10, $first->getQuantity());
    }

    public function testUpdateFailsIfBlogNumberIsMissing(): void
    {
        $id = $this->createBlog();
        $secondId = $this->createBlog();

        $context = Context::createDefaultContext();

        $orderId = $this->orderBlog($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);

        $first = $lineItems->first();
        static::assertNotNull($first);
        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getBlogId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('blogNumber', $first->getPayload());

        $this->expectExceptionObject(
            (new WriteException())
                ->add(new BlogLineItemDifferentIdException($first->getId()))
                ->add(new BlogLineItemInconsistentException($first->getId()))
        );

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'blogId' => $secondId],
        ], $context);
    }

    public function testSwitchLineItemBlog(): void
    {
        $id = $this->createBlog();
        $secondId = $this->createBlog();

        $context = Context::createDefaultContext();

        $orderId = $this->orderBlog($id, 5, $this->context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderId', $orderId));

        $lineItems = $this->lineItemRepository->search($criteria, $context)->getEntities();

        static::assertCount(1, $lineItems);
        $first = $lineItems->first();
        static::assertNotNull($first);
        static::assertSame($id, $first->getReferencedId());
        static::assertSame($id, $first->getBlogId());
        static::assertIsArray($first->getPayload());
        static::assertArrayHasKey('blogNumber', $first->getPayload());

        $this->lineItemRepository->update([
            ['id' => $first->getId(), 'blogId' => $secondId, 'referencedId' => $secondId, 'payload' => ['blogNumber' => $secondId]],
        ], $context);
    }

    private function orderBlog(string $id, int $quantity, ChannelContext $context): string
    {
        $factory = new BlogLineItemFactory(new PriceDefinitionFactory());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        $cart = $this->cartService->add($cart, $factory->create(['id' => $id, 'referencedId' => $id, 'quantity' => $quantity], $context), $context);

        $item = $cart->get($id);
        static::assertInstanceOf(LineItem::class, $item);
        static::assertSame($quantity, $item->getQuantity());

        return $this->cartService->order($cart, $context, new RequestDataBag());
    }

    private function createUser(): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
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
                    'firstName' => 'Max',
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
     * @param array<string|int, mixed|null> $config
     */
    private function createBlog(array $config = []): string
    {
        $id = Uuid::randomHex();

        $blog = [
            'id' => $id,
            'blogNumber' => $id,
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
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

        $this->repository->create([$blog], Context::createDefaultContext());
        $this->addTaxDataToChannel($this->context, $blog['tax']);

        return $id;
    }
}

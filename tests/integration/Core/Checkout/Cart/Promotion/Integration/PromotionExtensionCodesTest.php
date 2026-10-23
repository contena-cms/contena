<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Order\OrderConverter;
use Contena\Core\Checkout\Cart\Rule\LineItemTotalPriceRule;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionExtensionCodesTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    protected EntityRepository $blogRepository;

    protected CartService $cartService;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    protected EntityRepository $promotionRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->addCountriesToChannel();

        $this->context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // make sure we always start with a fresh cart
        $this->cartService->createNew($this->context->getToken());
    }

    /**
     * This test verifies that our cart service does correctly
     * add our code to the cart within the extension.
     * We do not assert the final price here, only that the code is
     * correctly added
     */
    #[Group('promotions')]
    public function testAddLineItemAddsToExtension(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertTrue($extension->hasCode($promotionCode));
    }

    /**
     * This test verifies that our cart services
     * does also correctly remove the matching code
     * within our extension, if existing.
     * We add a blog and promotion code, then we grab the promotion
     * line item id and remove it.
     * After that we verify that our code array is empty in our extension.
     */
    #[Group('promotions')]
    public function testDeleteLineItemRemovesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->remove($cart, $discountId, $this->context);

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertCount(0, $extension->getCodes());
    }

    /**
     * This test verifies that we successfully block any promotion
     * that does not have a code but gets removed by the admin_user.
     * In this case the promotion must not be added automatically again and again.
     */
    #[Group('promotions')]
    public function testAutoPromotionGetsBlockedWhenDeletingItem(): void
    {
        Feature::skipTestIfActive('PERMANENT_AUTOMATIC_PROMOTIONS', $this);

        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, null, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->remove($cart, $discountId, $this->context);

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertTrue($extension->isPromotionBlocked($promotionId));
    }

    #[Group('promotions')]
    public function testAutoPromotionCannotBeRemoved(): void
    {
        Feature::skipTestIfInActive('PERMANENT_AUTOMATIC_PROMOTIONS', $this);

        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion($promotionId, null, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        $discountLineItem = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first();
        static::assertInstanceOf(LineItem::class, $discountLineItem);
        static::assertFalse($discountLineItem->isRemovable());

        $this->expectExceptionObject(CartException::lineItemNotRemovable($discountLineItem->getId()));
        $this->cartService->remove($cart, $discountLineItem->getId(), $this->context);
    }

    /**
     * This test verifies that we can remove a line item
     * and then add that promotion again. In this case we
     * should have the code again in our extension.
     */
    #[Group('promotions')]
    public function testDeleteLineItemAndAddItAgainWorks(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->remove($cart, $discountId, $this->context);

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertCount(0, $extension->getCodes());

        $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertCount(1, $extension->getCodes());
    }

    #[Group('promotions')]
    public function testResetCodesAfterOrder(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [ChannelContextService::CUSTOMER_ID => $this->createUser()]
            );

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $context);

        $extension = $cart->getExtension(CartExtension::KEY);
        static::assertInstanceOf(CartExtension::class, $extension);

        $before = $extension->getCodes();
        static::assertNotCount(0, $before);

        /** @var string $discountId */
        $discountId = array_keys($cart->getLineItems()->getElements())[1];

        $this->cartService->order($cart, $context, new RequestDataBag());

        $this->cartService->remove($cart, $discountId, $context);

        $after = $extension->getCodes();
        static::assertCount(0, $after);
    }

    public function testRecalculatePromotionsWithSkippedPrivilege(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [ChannelContextService::CUSTOMER_ID => $this->createUser()]
            );

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $context);

        $orderId = $this->cartService->order($cart, $context, new RequestDataBag());

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('transactions')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.positions.orderLineItem')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        /** @var OrderEntity $order */
        $order = static::getContainer()->get('order.repository')
            ->search($criteria, $context->getContext())->getEntities()
            ->get($orderId);
        static::assertNotNull($order);

        $cart = static::getContainer()->get(OrderConverter::class)
            ->convertToCart($order, $context->getContext());

        $context->setPermissions([
            PromotionProcessor::SKIP_PROMOTION => true,
        ]);

        $cart = $this->cartService->recalculate($cart, $context);

        static::assertCount(2, $cart->getLineItems());
        $promotion = $cart->getLineItems()->filterType(LineItem::PROMOTION_LINE_ITEM_TYPE);
        static::assertCount(1, $promotion, 'Promotion was removed');
    }

    /**
     * This test verifies that our cart services
     * does also correctly remove the matching code
     * within our extension, if existing AND a fixed discount has been added that
     * is discounting TWO blogs.
     * We add two blogs and promotion code, then we grab one promotion discount
     * line item id and remove it.
     * After that we verify that our code array is empty in our extension (both discounts on the
     * two blogs are removed).
     */
    #[Group('promotions')]
    public function testDeleteLineItemFixedDiscountByCode(): void
    {
        $blogId = Uuid::randomHex();
        $blogTwoId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 100, 19, static::getContainer(), $this->context);

        // add a new sample blog
        $this->createTestFixtureBlog($blogTwoId, 100, 7, static::getContainer(), $this->context);

        // add a new promotion black friday
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 30, PromotionDiscountEntity::SCOPE_CART, $promotionCode, static::getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blog to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        // add blog to cart
        $cart = $this->addBlog($blogTwoId, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        $promotionItems = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE);

        /** @var string $discountId */
        $discountId = array_keys($promotionItems->getElements())[0];

        $this->cartService->remove($cart, $discountId, $this->context);

        /** @var CartExtension $extension */
        $extension = $cart->getExtension(CartExtension::KEY);

        static::assertCount(0, $extension->getCodes());
    }

    /**
     * This test verifies that a promotion get added again
     * if conditions are met again.
     * If a admin_user adds a promotion by code, this code should be
     * persistent in the cart. So if the promotion gets removes because of
     * a change in our blog line items, it should be added automatically
     * again if the blog conditions are back.
     * This improves the UX because the admin_user doesn't have to re-enter a code.
     */
    #[Group('promotions')]
    public function testAutoAddingOfPreviousCodes(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 30, 19, static::getContainer(), $this->context);

        // add a new promotion with a
        // minimum line item quantity discount rule of 2
        $this->createTestFixturePercentagePromotion($promotionId, $promotionCode, 50, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blog to cart with
        // a total price of more than our minimum price rule
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        static::assertCount(1, $cart->getLineItems());

        // add promotion to cart
        // because we have a price above our rule limit, it should be immediately discounted
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());

        // now remove item again and make sure promotion is gone
        $cart = $this->cartService->remove($cart, $blogId, $this->context);

        static::assertCount(0, $cart->getLineItems());

        // add our blog again and check if our promotion is back
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
    }

    public function testUsageOfCodeWithActiveNoCodePromo(): void
    {
        $blogCheap = Uuid::randomHex();
        $blogExpensive = Uuid::randomHex();
        $promotion1 = Uuid::randomHex();
        $promotion2 = Uuid::randomHex();
        $promotionCode = 'TEST123';

        $this->createTestFixtureBlog($blogCheap, 2, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogExpensive, 200, 19, static::getContainer(), $this->context);

        // create rule
        $ruleId = Uuid::randomHex();

        $ruleRepository = static::getContainer()->get('rule.repository');
        $conditionRepository = static::getContainer()->get('rule_condition.repository');

        $ruleRepository->create([
            [
                'id' => $ruleId,
                'name' => 'Cart >= 200',
                'priority' => 1,
            ],
        ], $this->context->getContext());

        $conditionRepository->create([
            [
                'id' => Uuid::randomHex(),
                'type' => (new LineItemTotalPriceRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_GTE,
                    'amount' => 200,
                ],
            ],
        ], $this->context->getContext());

        $this->createCustomPercentagePromotion($promotion1, 'Promo 1', null, 10, null, [
            'cartRules' => [
                ['id' => $ruleId],
            ],
        ]);
        $this->createCustomPercentagePromotion($promotion2, 'Promo 2', $promotionCode, 10, null, [
            'exclusionIds' => [
                $promotion1,
            ],
        ]);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add expensive blog to cart, promo 1 should be applied now
        $cart = $this->addBlog($blogExpensive, 1, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
        static::assertNotNull($cart->getLineItems()->last());
        static::assertSame('Promo 1', $cart->getLineItems()->last()->getLabel());

        // add promotion to cart
        // because we have another rule that cannot be combined, it is not applied
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
        static::assertNotNull($cart->getLineItems()->last());
        static::assertSame('Promo 1', $cart->getLineItems()->last()->getLabel());

        // now remove item again and make sure promotion is gone
        $cart = $this->cartService->remove($cart, $blogExpensive, $this->context);

        static::assertCount(0, $cart->getLineItems());

        // add cheap blog to check if our code will be applied
        $cart = $this->addBlog($blogCheap, 1, $cart, $this->cartService, $this->context);

        static::assertCount(2, $cart->getLineItems());
        static::assertNotNull($cart->getLineItems()->last());
        static::assertSame('Promo 2', $cart->getLineItems()->last()->getLabel());

        $cart = $this->addBlog($blogExpensive, 1, $cart, $this->cartService, $this->context);

        static::assertCount(3, $cart->getLineItems());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCustomPercentagePromotion(string $promotionId, string $name, ?string $code, float $percentage, ?float $maxValue, array $data = []): string
    {
        $data = array_merge([
            'id' => $promotionId,
            'name' => $name,
            'active' => true,
            'channels' => [
                ['channelId' => $this->context->getChannelId(), 'priority' => 1],
            ],
        ], $data);

        if ($code !== null) {
            $data['code'] = $code;
            $data['useCodes'] = true;
        }

        $this->createPromotionWithCustomData($data, $this->promotionRepository, $this->context);

        return $this->createTestFixtureDiscount($promotionId, PromotionDiscountEntity::TYPE_PERCENTAGE, PromotionDiscountEntity::SCOPE_CART, $percentage, $maxValue, static::getContainer(), $this->context);
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
}

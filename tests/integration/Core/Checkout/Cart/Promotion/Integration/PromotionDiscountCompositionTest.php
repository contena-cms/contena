<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Checkout\Promotion\PromotionEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionDiscountCompositionTest extends TestCase
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

        $this->context = static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    /**
     * This test verifies that we have a correct composition node in the payload.
     * We use this as reference, to know of what blogs and quantities our discount consists of.
     * The absolute discount needs to contain all blogs and items in the composition. The price of these
     * composition-blogs need to be divided individually across all included blogs.
     * We have a blog with price 50 EUR and quantity 3 and another blog with price 100 and quantity 1.
     * If we have an absolute discount of 30 EUR, then blog one should be referenced with 18 EUR and blog 2 with 12 EUR (150 EUR vs. 100 EUR).
     **/
    #[Group('promotions')]
    public function testCompositionInAbsoluteDiscount(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId1, 50, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogId2, 100, 19, static::getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureAbsolutePromotion($promotionId, $code, 30, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        $composition = $discountItem->getPayload()['composition'];

        static::assertSame($blogId1, $composition[0]['id']);
        static::assertSame(3, $composition[0]['quantity']);
        static::assertSame(18.0, $composition[0]['discount']);

        static::assertSame($blogId2, $composition[1]['id']);
        static::assertSame(1, $composition[1]['quantity']);
        static::assertSame(12.0, $composition[1]['discount']);
    }

    /**
     * This test verifies that our composition data is correct.
     * We apply a discount of 25% on all items. So every item should appear with its original
     * quantity and the 25% of its original price as discount.
     **/
    #[Group('promotions')]
    public function testCompositionInPercentageDiscount(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId1, 50, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogId2, 100, 19, static::getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixturePercentagePromotion($promotionId, $code, 25, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        $composition = $discountItem->getPayload()['composition'];

        static::assertSame($blogId1, $composition[0]['id']);
        static::assertSame(3, $composition[0]['quantity']);
        static::assertSame(150 * 0.25, $composition[0]['discount']);

        static::assertSame($blogId2, $composition[1]['id']);
        static::assertSame(1, $composition[1]['quantity']);
        static::assertSame(100 * 0.25, $composition[1]['discount']);
    }

    public function testPromotionRedemption(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [ChannelContextService::CUSTOMER_ID => $this->createUser()]
            );
        $userId1 = $context->getUserId();
        static::assertNotNull($userId1);

        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId1, 50, 19, static::getContainer(), $context);
        $this->createTestFixtureBlog($blogId2, 100, 19, static::getContainer(), $context);

        // add a new promotion
        $this->createTestFixturePercentagePromotion($promotionId, $code, 25, null, static::getContainer());

        // order promotion with two blogs
        $this->orderWithPromotion($code, [$blogId1, $blogId2], $context);

        $promotion = $this->promotionRepository
            ->search(new Criteria([$promotionId]), Context::createDefaultContext())->getEntities()
            ->get($promotionId);

        static::assertInstanceOf(PromotionEntity::class, $promotion);

        // verify that the promotion has a total order count of 1 and the current user is although tracked
        static::assertSame(1, $promotion->getOrderCount());
        static::assertNotNull($context->getUser());
        static::assertSame(
            [$userId1 => 1],
            $promotion->getOrdersPerUserCount()
        );

        // order promotion with two blogs
        $this->orderWithPromotion($code, [$blogId1, $blogId2], $context);

        $promotion = $this->promotionRepository
            ->search(new Criteria([$promotionId]), Context::createDefaultContext())->getEntities()
            ->get($promotionId);
        static::assertNotNull($promotion);

        // verify that the promotion has a total order count of 1 and the current user is although tracked
        static::assertSame(2, $promotion->getOrderCount());
        static::assertSame(
            [$userId1 => 2],
            $promotion->getOrdersPerUserCount()
        );

        $context2 = static::getContainer()->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [ChannelContextService::CUSTOMER_ID => $this->createUser()]
            );

        static::assertNotNull($context2->getUser());
        // order promotion with two blogs and another user
        $this->orderWithPromotion($code, [$blogId1, $blogId2], $context2);

        $promotion = $this->promotionRepository
            ->search(new Criteria([$promotionId]), Context::createDefaultContext())->getEntities()
            ->get($promotionId);
        static::assertNotNull($promotion);

        static::assertSame(3, $promotion->getOrderCount());
        $expected = [
            $context2->getUser()->getId() => 1,
            $userId1 => 2,
        ];

        $actual = $promotion->getOrdersPerUserCount() ?? [];

        ksort($expected);
        ksort($actual);
        static::assertSame($expected, $actual);
    }

    /**
     * This test verifies that our composition data is correct.
     * We apply a discount that sells every item for 10 EUR.
     * We have a blog with quantity 3 and total of 150 EUR and another blog with 100 EUR.
     * Both our composition entries should have a discount of 120 (-3x10) and 90 EUR (-1x10).
     **/
    #[Group('promotions')]
    public function testCompositionInFixedUnitDiscount(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId1, 50, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogId2, 100, 19, static::getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedUnitDiscountPromotion($promotionId, 10, PromotionDiscountEntity::SCOPE_CART, $code, static::getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        $composition = $discountItem->getPayload()['composition'];

        static::assertSame($blogId1, $composition[0]['id']);
        static::assertSame(3, $composition[0]['quantity']);
        static::assertSame(120.0, $composition[0]['discount']);

        static::assertSame($blogId2, $composition[1]['id']);
        static::assertSame(1, $composition[1]['quantity']);
        static::assertSame(90.0, $composition[1]['discount']);
    }

    /**
     * This test verifies that our composition data is correct.
     * We apply a discount that sells all item for a total of 70 EUR.
     * We have a blog with quantity 3 and total of 150 EUR and another blog with 100 EUR.
     * Both our composition entries should have a discount of 108 and 72 EUR which should
     * make the rest of it a total of 70 EUR.
     * The calculation is based on their proportionate distribution.
     **/
    #[Group('promotions')]
    public function testCompositionInFixedDiscount(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample blog
        $this->createTestFixtureBlog($blogId1, 50, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogId2, 100, 19, static::getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 70, PromotionDiscountEntity::SCOPE_CART, $code, static::getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId1, 3, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogId2, 1, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        // get discount line item
        $discountItem = $cart->getLineItems()->getFlat()[2];

        static::assertTrue($discountItem->hasPayloadValue('composition'), 'composition node is missing');

        $composition = $discountItem->getPayload()['composition'];

        static::assertSame($blogId1, $composition[0]['id']);
        static::assertSame(3, $composition[0]['quantity']);
        static::assertSame(108.0, $composition[0]['discount']);

        static::assertSame($blogId2, $composition[1]['id']);
        static::assertSame(1, $composition[1]['quantity']);
        static::assertSame(72.0, $composition[1]['discount']);
    }

    /**
     * @param array<string> $blogIds
     */
    private function orderWithPromotion(string $code, array $blogIds, ChannelContext $context): string
    {
        $cart = $this->cartService->createNew($context->getToken());

        foreach ($blogIds as $blogId) {
            $cart = $this->addBlog($blogId, 3, $cart, $this->cartService, $context);
        }

        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        $promotions = $cart->getLineItems()->filterType('promotion');
        static::assertCount(1, $promotions);

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
}

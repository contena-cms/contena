<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionFixedPriceCalculationTest extends TestCase
{
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

        $this->context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We test that we always end with a final price of our promotion if that one is set to a "fixed price".
     * Our price would be 40 EUR. It must not matter how many items and blogs we have in there,
     * the final price should always be 40 EUR.
     */
    #[Group('promotions')]
    public function testFixedUnitDiscount(): void
    {
        $blogId = Uuid::randomHex();
        $blogIdTwo = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add a new sample blogs
        $this->createTestFixtureBlog($blogId, 100, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogIdTwo, 100, 7, static::getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, $code, static::getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add blogs to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogIdTwo, 1, $cart, $this->cartService, $this->context);

        // add promotion to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertSame(40.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(40.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(35.49, $cart->getPrice()->getNetPrice());
    }

    /**
     * if a automatic fixed price promotion (no code necessary) discount is removed
     * it should not be added again. This is a new feature - to block automatic promotions.
     */
    #[Group('promotions')]
    public function testRemoveOfFixedUnitPromotionsWithoutCode(): void
    {
        Feature::skipTestIfActive('PERMANENT_AUTOMATIC_PROMOTIONS', $this);

        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 100, 19, static::getContainer(), $context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, null, static::getContainer(), $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create first blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        static::assertCount(2, $cart->getLineItems(), 'We expect two lineItems in cart');

        static::assertSame(40.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(40.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(33.61, $cart->getPrice()->getNetPrice(), 'Discounted cart does not have expected net price');

        $discountLineItem = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first();
        static::assertNotNull($discountLineItem);
        $discountId = $discountLineItem->getId();

        // and try to remove promotion
        $cart = $this->cartService->remove($cart, $discountId, $context);

        static::assertCount(1, $cart->getLineItems(), 'We expect 1 lineItem in cart');

        static::assertSame(100.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(100.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(84.03, $cart->getPrice()->getNetPrice(), 'Even after promotion delete try it should be present and blog should be discounted');
    }

    #[Group('promotions')]
    public function testAutomaticFixedUnitPromotionsWithoutCodeAreNotRemovable(): void
    {
        Feature::skipTestIfInActive('PERMANENT_AUTOMATIC_PROMOTIONS', $this);

        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $this->createTestFixtureBlog($blogId, 100, 19, static::getContainer(), $context);
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 40, PromotionDiscountEntity::SCOPE_CART, null, static::getContainer(), $context);

        $cart = $this->cartService->getCart($context->getToken(), $context);
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        $discountLineItem = $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first();
        static::assertNotNull($discountLineItem);
        static::assertFalse($discountLineItem->isRemovable());

        static::assertSame(40.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(40.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(33.61, $cart->getPrice()->getNetPrice(), 'Discounted cart does not have expected net price');

        $this->expectExceptionObject(CartException::lineItemNotRemovable($discountLineItem->getId()));
        $this->cartService->remove($cart, $discountLineItem->getId(), $context);
    }

    /**
     * This test verifies that we use the max value of our currency
     * instead of the defined fixed price, if existing.
     * Thus we create a promotion with a fixed price of 80
     * That would lead to 80 EUR for the blog
     * But for your currency defined price, we use 65 as fixed price instead.
     * Our test needs to verify that we use 30 EUR, and end with a blog sum of 65 EUR in the end.
     */
    #[Group('promotions')]
    public function testFixedUnitPriceDiscountWithCurrencyPrices(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $blogGross = 100.0;
        $fixedPriceValue = 80;
        $currencyMaxValue = 65.0;

        $expectedPrice = $currencyMaxValue;

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, $blogGross, 19, static::getContainer(), $context);

        $discountId = $this->createTestFixtureFixedDiscountPromotion($promotionId, $fixedPriceValue, PromotionDiscountEntity::SCOPE_CART, $code, static::getContainer(), $context);

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        static::assertSame($blogGross, $cart->getPrice()->getTotalPrice());

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame($expectedPrice, $cart->getPrice()->getPositionPrice());
        static::assertSame($expectedPrice, $cart->getPrice()->getTotalPrice());
        static::assertSame(54.62, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we can assign a fixed total price for our promotion discount.
     * We test this by adding a scope of the "entire cart" which consists of 2 blogs with different quantities.
     * Then we add a promotion with a fixed price of 100 EUR.
     * This means that our final cart price should be 100 EUR and the discount need to be calculated correctly
     * by considering the existing cart items.
     */
    #[Group('promotions')]
    public function testFixedCartPriceDiscount(): void
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF19';

        // add 2 test blogs
        $this->createTestFixtureBlog($blogId1, 200, 19, static::getContainer(), $this->context);
        $this->createTestFixtureBlog($blogId2, 50, 19, static::getContainer(), $this->context);

        // add a new promotion
        $this->createTestFixtureFixedDiscountPromotion($promotionId, 100, PromotionDiscountEntity::SCOPE_CART, $code, static::getContainer(), $this->context);

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // create first blog and add to cart
        $cart = $this->addBlog($blogId1, 2, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogId2, 3, $cart, $this->cartService, $this->context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertCount(3, $cart->getLineItems(), 'We expect 2 blogs and 1 discount in cart');

        static::assertSame(100.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(100.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(84.03, $cart->getPrice()->getNetPrice());
    }
}

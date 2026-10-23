<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Ticket;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Channel\CartService;
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
class PromotionPercentageCalculationTest extends TestCase
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

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a blog to the cart and apply a code for a promotion with 100% discount.
     * Our cart should have a total value of 0,00 in the end.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function test100PercentageDiscount(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 29, 17, static::getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame(0.0, $cart->getPrice()->getPositionPrice(), 'Position Total Price has to be 0,00');
        static::assertSame(0.0, $cart->getPrice()->getTotalPrice(), 'Total Price has to be 0,00');
        static::assertSame(0.0, $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes have to be 0,00');
        static::assertSame(0.0, $cart->getPrice()->getNetPrice(), 'Net Price has to be 0,00');
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a blog to the cart and apply a code for a promotion with 50% discount.
     * Our cart should have a total value of 15,00 in the end.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function test50PercentageDiscount(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 100, 20, static::getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, null, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        /**
         * Tax rate: 20%
         *
         * 100€ blog (gross)
         *          => included taxes: 16.6666666667 => 16.67€
         *          => net price: 83.33€
         *
         * -50% discount promotion
         *      gross: 50€
         *      included taxes: 8.33333333333 => 8.33€
         *      net price: 41.67€
         *
         * Total price:
         *      gross: 50€
         *      included taxes: 8.34
         *      net price: 41.66€
         */
        static::assertSame(50.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(50.0, $cart->getPrice()->getPositionPrice());
        // v6.8: PercentagePriceCalculator scales and rounds each calculated tax instead of recalculating,
        // so the included tax of the discount rounds to 8.33 instead of 8.34
        static::assertSame(Feature::isActive('PROPORTIONAL_CART_TAXES') ? 41.67 : 41.66, $cart->getPrice()->getNetPrice());

        $promotion = $cart->getLineItems()->getElements();
        $promotion = array_values($promotion)[1];

        static::assertInstanceOf(LineItem::class, $promotion);
        $price = $promotion->getPrice();
        static::assertInstanceOf(CalculatedPrice::class, $price);
        static::assertSame(-50.0, $price->getTotalPrice());
        static::assertNotNull($price->getCalculatedTaxes()->first());
        static::assertSame(Feature::isActive('PROPORTIONAL_CART_TAXES') ? -8.34 : -8.33, $price->getCalculatedTaxes()->first()->getTax());
    }

    /**
     * This test verifies that we can set a
     * maximum absolute value for a percentage discount.
     * We have a 100 EUR blog and 50% OFF but a maximum
     * of 30 EUR discount. This means our cart should be minimum 70 EUR in the end.
     * We have
     */
    #[Group('promotions')]
    public function test50PercentageDiscountWithMaximumValue(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 100, 20, static::getContainer(), $context);

        // add a new promotion with 50% discount but a maximum of 30 EUR.
        // our blog costs 100 EUR, which should now be 70 EUR due to the threshold
        $this->createTestFixturePercentagePromotion($promotionId, $code, 50, 30.0, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame(70.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(70.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(58.33, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we use the max value of our currency
     * instead of the global max value, if existing.
     * Thus we create a promotion with 50% for a 100 EUR price.
     * That would lead to 50 EUR for the blog, which we avoid by setting
     * a max global threshold of 40 EUR.
     * But for your currency, we use 30 EUR instead.
     * Our test needs to verify that we use 30 EUR, and end with a blog sum of 70 EUR in the end.
     */
    #[Group('promotions')]
    public function test50PercentageDiscountWithMaximumValueAndCurrencies(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $blogGross = 100;
        $percentage = 50;
        $maxValueGlobal = 40.0;
        $currencyMaxValue = 30.0;

        $expectedPrice = $blogGross - $currencyMaxValue;

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, $blogGross, 19, static::getContainer(), $context);

        $discountId = $this->createTestFixturePercentagePromotion($promotionId, $code, $percentage, $maxValueGlobal, static::getContainer());

        $this->createTestFixtureAdvancedPrice($discountId, Defaults::CURRENCY, $currencyMaxValue, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame($expectedPrice, $cart->getPrice()->getPositionPrice());
        static::assertSame($expectedPrice, $cart->getPrice()->getTotalPrice());
        static::assertSame(58.82, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that we have fixed our division by zero problem in percentage calculations.
     * That case is happening in very rare scenarios where somehow the
     * blog total sum is 0,00 but we still have a promotion that will be calculated.
     * We fake a blog with 0,00 price and just try to add our promotion in here.
     * We must not get a division by zero!
     */
    #[Ticket('NEXT-4146')]
    #[Group('promotions')]
    public function testPercentagePromotionDivisionByZero(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 0, 19, static::getContainer(), $context);
        // add a new percentage promotion
        $this->createTestFixturePercentagePromotion($promotionId, $code, 100.0, 100.0, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        // make sure we have only 1 blog line item in there
        static::assertCount(1, $cart->getLineItems());

        // now just try to see if we have a valid 0,00 total price
        static::assertSame(0.0, $cart->getPrice()->getTotalPrice());
    }
}

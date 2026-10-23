<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\Builder\Promotion\PromotionFixtureBuilder;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class PromotionSetGroupCalculationTest extends TestCase
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

    private ChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);

        $this->context = $this->getContext();
    }

    /**
     * This test verifies that we give correct percentage discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different blogs in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the blogs with quantity 2.
     * We give 100% discount on that package, which means the user has to
     * only pay the 1 blog that is left.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testPercentageOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = static::getContainer();
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureBlog($blogId1, 65, 19, $container, $this->context);
        $this->createTestFixtureBlog($blogId2, 30, 7, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_PERCENTAGE, 100.0, false, null);
        $cart = $this->getCart($promotionBuilder, $blogId1, $blogId2, $code);

        static::assertSame(65.0, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertSame(65.0, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertSame(54.62, $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertSame(10.38, $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    /**
     * This test verifies that we give correct absolute discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different blogs in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the blogs with quantity 2.
     * We give 50 EUR discount on that package, which means the user has to
     * pay (blog 1 + blog 2 - 50) + blog 2.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testAbsoluteOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = static::getContainer();
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureBlog($blogId1, 60, 19, $container, $this->context);
        $this->createTestFixtureBlog($blogId2, 30, 19, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_ABSOLUTE, 50.0, false, null);
        $cart = $this->getCart($promotionBuilder, $blogId1, $blogId2, $code);

        // total is the sum of p1 + p2 minus the absolute + the last blog
        $expectedTotal = (30.0 + 60.0 - 50.0) + 60.0;
        // net price is both prices of p1 and p2 minus 50 and their net value......+ the net price of the last blog
        $expectedNetPrice = ((30.0 + 60.0 - 50.0) / 119.0 * 100.0) + (60.0 / 119.0 * 100.0);
        // taxes should be the difference
        $expectedTaxes = $expectedTotal - $expectedNetPrice;

        static::assertSame($expectedTotal, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertSame($expectedTotal, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertSame(round($expectedNetPrice, 2), $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertSame(round($expectedTaxes, 2), $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    /**
     * This test verifies that we give correct absolute discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different blogs in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the blogs with quantity 2.
     * We give 20 EUR fixed count on every blog in the group, which means the user has to
     * pay 20 EUR + 20 EUR + blog 2.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testFixedUnitPriceOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = static::getContainer();
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureBlog($blogId1, 60, 19, $container, $this->context);
        $this->createTestFixtureBlog($blogId2, 30, 19, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_FIXED_UNIT, 20.0, false, null);
        $cart = $this->getCart($promotionBuilder, $blogId1, $blogId2, $code);

        // total is the sum of p1 + p2 minus the absolute + the last blog
        $expectedTotal = (20.0 + 20.0) + 60.0;
        // net price is both prices of p1 and p2 minus 50 and their net value......+ the net price of the last blog
        $expectedNetPrice = ((20.0 + 20.0) / 119.0 * 100.0) + (60.0 / 119.0 * 100.0);
        // taxes should be the difference
        $expectedTaxes = $expectedTotal - $expectedNetPrice;

        static::assertSame($expectedTotal, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertSame($expectedTotal, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertSame(round($expectedNetPrice, 2), $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertSame(round($expectedTaxes, 2), $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    /**
     * This test verifies that we give correct absolute discounts if the
     * set group consists of different line items and custom quantities.
     * We have a package of 2 of the cheapest items.
     * We only have 2 different blogs in our cart with total quantity 3 (1x and 2x).
     * Our cheapest 2 items are 1x the item with quantity 1 and then only 1x
     * the item of the blogs with quantity 2.
     * We give 50 EUR fixed price for the whole package, which means the user has to
     * pay 50 EUR + blog 2.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testFixedPriceOnMultipleItemsAndSubsetQuantities(): void
    {
        $container = static::getContainer();
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();

        $code = 'BF' . Random::getAlphanumericString(5);

        // prepare promotion
        $this->createTestFixtureBlog($blogId1, 60, 19, $container, $this->context);
        $this->createTestFixtureBlog($blogId2, 30, 19, $container, $this->context);

        // prepare a percentage promotion with 100% OFF
        // with a set group of the 2 cheapest items.
        $promotionBuilder = $this->createPromotionFixtureBuilder($container)
            ->addSetGroup('COUNT', 2, 'PRICE_ASC')
            ->setCode($code)
            ->addDiscount(PromotionDiscountEntity::SCOPE_SET, PromotionDiscountEntity::TYPE_FIXED, 50.0, false, null);
        $cart = $this->getCart($promotionBuilder, $blogId1, $blogId2, $code);

        // total is the sum of p1 + p2 minus the absolute + the last blog
        $expectedTotal = 50.0 + 60.0;
        // net price is both prices of p1 and p2 minus 50 and their net value......+ the net price of the last blog
        $expectedNetPrice = (50.0 / 119.0 * 100.0) + (60.0 / 119.0 * 100.0);
        // taxes should be the difference
        $expectedTaxes = $expectedTotal - $expectedNetPrice;

        static::assertSame($expectedTotal, $cart->getPrice()->getPositionPrice(), 'Position Total Price is wrong');
        static::assertSame($expectedTotal, $cart->getPrice()->getTotalPrice(), 'Total Price is wrong');
        static::assertSame(round($expectedNetPrice, 2), $cart->getPrice()->getNetPrice(), 'Net Price is wrong');
        static::assertSame(round($expectedTaxes, 2), $cart->getPrice()->getCalculatedTaxes()->getAmount(), 'Taxes are wrong');
    }

    protected function createPromotionFixtureBuilder(ContainerInterface $container): PromotionFixtureBuilder
    {
        return new PromotionFixtureBuilder(
            Uuid::randomHex(),
            $container->get(ChannelContextFactory::class),
            $container->get('promotion.repository'),
            $container->get('promotion_setgroup.repository'),
            $container->get('promotion_discount.repository')
        );
    }

    protected function getCart(
        PromotionFixtureBuilder $promotionBuilder,
        string $blogId1,
        string $blogId2,
        string $code
    ): Cart {
        $promotionBuilder->buildPromotion();

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add 3 items to our cart
        // the cheapest one 1x and 2x the other blog
        $cart = $this->addBlog($blogId1, 2, $cart, $this->cartService, $this->context);
        $cart = $this->addBlog($blogId2, 1, $cart, $this->cartService, $this->context);

        // add our promotion
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        return $cart;
    }
}

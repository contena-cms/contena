<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration\Calculation;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\CartException;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionAbsoluteCalculationTest extends TestCase
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
     * This test verifies that our absolute promotions are correctly added.
     * We add a blog and also an absolute promotion.
     * Our final price should then be as expected.
     *
     * @throws CartException
     */
    #[Group('promotions')]
    public function testAbsoluteDiscount(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 60, 17, static::getContainer(), $context);

        // add a new promotion black friday
        $this->createTestFixtureAbsolutePromotion($promotionId, $code, 45, static::getContainer());

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 2, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame(75.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(75.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(64.1, $cart->getPrice()->getNetPrice());
    }

    /**
     * This test verifies that our promotion components are really involved in our checkout.
     * We add a blog to the cart and apply a code for a promotion with a currency dependent discount.
     * The standard value of discount would be 15, but our currency price value is 30
     * Our cart should have a total value of 70,00 (and not 85 as standard) in the end.
     */
    #[Group('promotions')]
    public function testAbsoluteDiscountWithCurrencyPriceValues(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        // add a new sample blog
        $this->createTestFixtureBlog($blogId, 100, 19, static::getContainer(), $context);

        $this->createAdvancedCurrencyPriceValuePromotion($promotionId, $code, 15, 30);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame(70.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(70.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(58.82, $cart->getPrice()->getNetPrice());
    }

    public function testNetUserAbsoluteDiscountHigherThanCartTotal(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $code = 'BF' . Random::getAlphanumericString(5);

        $context = static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [ChannelContextService::CUSTOMER_ID => $this->createNetUser()]
            );

        $this->createTestFixtureBlog($blogId, 100, 19, static::getContainer(), $context);

        $this->createAdvancedCurrencyPriceValuePromotion($promotionId, $code, 300, 600);

        $cart = $this->cartService->getCart($context->getToken(), $context);

        // create blog and add to cart
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $context);

        // create promotion and add to cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $context);

        static::assertSame(0.0, $cart->getPrice()->getPositionPrice());
        static::assertSame(0.0, $cart->getPrice()->getTotalPrice());
        static::assertSame(0.0, $cart->getPrice()->getNetPrice());
    }

    /**
     * create a promotion with a currency based price value discount.
     */
    private function createAdvancedCurrencyPriceValuePromotion(string $promotionId, string $code, float $discountPrice, float $advancedPrice): void
    {
        $discountId = Uuid::randomHex();

        $this->promotionRepository->create(
            [
                [
                    'id' => $promotionId,
                    'name' => 'Black Friday',
                    'active' => true,
                    'code' => $code,
                    'useCodes' => true,
                    'channels' => [
                        ['channelId' => TestDefaults::CHANNEL, 'priority' => 1],
                    ],
                    'discounts' => [
                        [
                            'id' => $discountId,
                            'scope' => PromotionDiscountEntity::SCOPE_CART,
                            'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                            'value' => $discountPrice,
                            'considerAdvancedRules' => false,
                            'promotionDiscountPrices' => [
                                [
                                    'currencyId' => Defaults::CURRENCY,
                                    'discountId' => $discountId,
                                    'price' => $advancedPrice,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );
    }

    private function createNetUser(): string
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
            'groupId' => $this->createNetUserGroup(),
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

    private function createNetUserGroup(): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'displayGross' => false,
            'translations' => [
                'en-GB' => [
                    'name' => 'Net price user group',
                ],
                'de-DE' => [
                    'name' => 'Nettopreis-Kundengruppe',
                ],
            ],
        ];

        static::getContainer()->get('user_group.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }
}

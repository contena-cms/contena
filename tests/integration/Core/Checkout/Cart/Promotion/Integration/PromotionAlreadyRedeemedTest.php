<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Contena\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Contena\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Contena\Core\Checkout\Promotion\DataAbstractionLayer\PromotionRedemptionUpdater;
use Contena\Core\Checkout\Promotion\PromotionCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
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
class PromotionAlreadyRedeemedTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var EntityRepository<PromotionCollection>
     */
    private EntityRepository $promotionRepository;

    private CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promotionRepository = static::getContainer()->get('promotion.repository');
        $this->cartService = static::getContainer()->get(CartService::class);
        $this->addCountriesToChannel();
    }

    /**
     * Reproduces the reported issue: a promotion with a code that is redeemable only
     * once per user. After the user redeems it and submits the order, applying
     * the same code again in a new checkout must report a clear "already redeemed"
     * reason instead of the misleading "promotion could not be found" error.
     */
    #[Group('promotions')]
    public function testReapplyingAOnceRedeemedCodeReportsAlreadyRedeemed(): void
    {
        $blogId = Uuid::randomHex();
        $promotionId = Uuid::randomHex();
        $promotionCode = 'TESTCODE';
        $userId = $this->createUser();

        // a promotion with a fixed (global) code, redeemable once per user, with a discount
        $this->promotionRepository->create([[
            'id' => $promotionId,
            'name' => 'Once per user',
            'active' => true,
            'code' => $promotionCode,
            'useCodes' => true,
            'useIndividualCodes' => false,
            'maxRedemptionsPerUser' => 1,
            'channels' => [
                ['channelId' => TestDefaults::CHANNEL, 'priority' => 1],
            ],
            'discounts' => [
                [
                    'scope' => PromotionDiscountEntity::SCOPE_CART,
                    'type' => PromotionDiscountEntity::TYPE_ABSOLUTE,
                    'value' => 10,
                    'considerAdvancedRules' => false,
                ],
            ],
        ]], Context::createDefaultContext());

        // 1) first checkout: redeem the code and submit the order
        $firstContext = $this->createUserContext($userId);
        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $firstContext);

        $cart = $this->cartService->getCart($firstContext->getToken(), $firstContext);
        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $firstContext);
        $cart = $this->addPromotionCode($promotionCode, $cart, $this->cartService, $firstContext);

        // the promotion is eligible and applied on the first redemption
        static::assertCount(
            1,
            $cart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE),
            'Promotion should be applied on the first redemption'
        );

        $this->cartService->order($cart, $firstContext, new RequestDataBag());

        // ensure the per-user redemption count reflects the placed order
        static::getContainer()->get(PromotionRedemptionUpdater::class)
            ->update([$promotionId], Context::createDefaultContext());

        // 2) new checkout with the same user: applying the same code again is rejected clearly
        $secondContext = $this->createUserContext($userId);
        $secondCart = $this->cartService->getCart($secondContext->getToken(), $secondContext);
        $secondCart = $this->addBlog($blogId, 1, $secondCart, $this->cartService, $secondContext);
        $secondCart = $this->addPromotionCode($promotionCode, $secondCart, $this->cartService, $secondContext);

        $error = $secondCart->getErrors()->get('promotion-not-eligible');
        static::assertInstanceOf(
            PromotionNotEligibleError::class,
            $error,
            'Expected a "not eligible" error on the second redemption attempt'
        );
        static::assertSame(
            'promotion-not-eligible-already-redeemed',
            $error->getMessageKey(),
            'The error must carry the "already redeemed" reason'
        );

        static::assertFalse(
            $secondCart->getErrors()->has('promotion-not-found'),
            'The misleading "promotion not found" error must no longer be used for a redeemed code'
        );

        static::assertCount(
            0,
            $secondCart->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE),
            'The exhausted promotion must not be applied again'
        );
    }

    private function createUserContext(string $userId): ChannelContext
    {
        return static::getContainer()->get(ChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::CHANNEL,
            [ChannelContextService::CUSTOMER_ID => $userId]
        );
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

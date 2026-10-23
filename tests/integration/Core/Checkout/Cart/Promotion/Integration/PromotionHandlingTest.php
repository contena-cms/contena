<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class PromotionHandlingTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = static::getContainer()->get(CartService::class);

        $this->context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    /**
     * This test verifies that our promotions are not added
     * if our cart is empty and has no blogs yet.
     */
    #[Group('promotions')]
    public function testPromotionNotAddedWithoutBlog(): void
    {
        $blogId = Uuid::randomHex();
        $code = 'BF19';

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $code, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        // add our promotion to our cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        static::assertCount(0, $cart->getLineItems());
    }

    /**
     * This test verifies that our promotions are correctly
     * removed when also removing the last blog
     */
    #[Group('promotions')]
    public function testPromotionsRemovedWithBlog(): void
    {
        $blogId = Uuid::randomHex();
        $code = 'BF19';

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->context);
        $this->createTestFixturePercentagePromotion(Uuid::randomHex(), $code, 100, null, static::getContainer());

        $cart = $this->cartService->getCart($this->context->getToken(), $this->context);

        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->context);

        // add our promotion to our cart
        $cart = $this->addPromotionCode($code, $cart, $this->cartService, $this->context);

        $ids = array_keys($cart->getLineItems()->getElements());
        static::assertArrayHasKey(0, $ids);

        // remove our first item (blog)
        $cart = $this->cartService->remove($cart, $ids[0], $this->context);

        static::assertCount(0, $cart->getLineItems());
    }
}

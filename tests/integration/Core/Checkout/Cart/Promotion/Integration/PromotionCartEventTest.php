<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Promotion\Integration;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Contena\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionIntegrationTestBehaviour;
use Contena\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;

/**
 * @internal
 */
class PromotionCartEventTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionIntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    protected CartService $cartService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartService = static::getContainer()->get(CartService::class);
    }

    /**
     * If we add more than two promotions we could encounter an infinite recursion if a promotion is added
     * via the cart service in the FrontendCartSubscriber.
     * For good measure, we add more than three promotions to the cart in this case.
     * Our main test case is to create additional dispatch listener that make sure our event is
     * only called once for every discount +1 for the actual blog.
     * We also must not call a line item removed event.
     */
    #[Group('promotions')]
    public function testAvoidInfiniteLoopEventsWithLotsOfPromotions(): void
    {
        $blogId = Uuid::randomHex();

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->getContext());

        $codes = [100, 1, 42, 13, 19];
        $this->createBulkPromotions($codes);

        $addListener = $this->createMock(CallableClass::class);
        $addListener->expects($this->exactly(1 + \count($codes)))->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on(BeforeLineItemAddedEvent::class, $addListener);

        $cart = $this->cartService->getCart($this->getContext()->getToken(), $this->getContext());

        // add all our prepared test fixtures
        // and promotions to our current cart.
        foreach ($codes as $code) {
            $cart = $this->addPromotionCode((string) $code, $cart, $this->cartService, $this->getContext());
        }

        // now add our blog which should trigger our event
        $this->addBlog($blogId, 1, $cart, $this->cartService, $this->getContext());
    }

    /**
     * This test verifies that we only fire our remove item
     * once, even though we have lots of promotions in our cart.
     */
    #[Group('promotions')]
    public function testAvoidInfiniteLoopEventsWhenRemovingLotsOfPromotions(): void
    {
        $blogId = Uuid::randomHex();

        $this->createTestFixtureBlog($blogId, 119, 19, static::getContainer(), $this->getContext());

        $codes = [100, 1, 42, 13, 19];
        $this->createBulkPromotions($codes);

        $removeListener = $this->createMock(CallableClass::class);
        $removeListener->expects($this->once())->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on(BeforeLineItemRemovedEvent::class, $removeListener);

        $cart = $this->cartService->getCart($this->getContext()->getToken(), $this->getContext());

        foreach ($codes as $code) {
            $cart = $this->addPromotionCode((string) $code, $cart, $this->cartService, $this->getContext());
        }

        $cart = $this->addBlog($blogId, 1, $cart, $this->cartService, $this->getContext());

        // now remove our cart, make sure our remove event is only called once
        $this->cartService->remove($cart, $blogId, $this->getContext());
    }

    /**
     * @param list<float> $codes
     */
    private function createBulkPromotions(array $codes): void
    {
        foreach ($codes as $percentage) {
            $this->createTestFixturePercentagePromotion(
                Uuid::randomHex(),
                (string) $percentage,
                $percentage,
                null,
                static::getContainer()
            );
        }
    }
}

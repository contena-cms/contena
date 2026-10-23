<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPage;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPageLoadedHook;

/**
 * @internal
 */
#[CoversClass(CheckoutCartPageLoadedHook::class)]
class CheckoutCartPageLoadedHookTest extends TestCase
{
    public function testNameRespectsCartSource(): void
    {
        $hook = $this->createHook();
        $hook->getCart()->setSource('test');

        static::assertStringEndsWith('-loaded-test', $hook->getName());
    }

    public function testNameWithoutCartSource(): void
    {
        static::assertStringEndsWith('-loaded', $this->createHook()->getName());
    }

    private function createHook(): CheckoutCartPageLoadedHook
    {
        return new CheckoutCartPageLoadedHook(
            (new CheckoutCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]),
            Generator::generateChannelContext(),
        );
    }
}

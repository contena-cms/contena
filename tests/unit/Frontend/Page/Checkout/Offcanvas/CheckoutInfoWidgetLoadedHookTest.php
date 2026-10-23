<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook;
use Contena\Frontend\Page\Checkout\Offcanvas\OffcanvasCartPage;

/**
 * @internal
 */
#[CoversClass(CheckoutInfoWidgetLoadedHook::class)]
class CheckoutInfoWidgetLoadedHookTest extends TestCase
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

    private function createHook(): CheckoutInfoWidgetLoadedHook
    {
        return new CheckoutInfoWidgetLoadedHook(
            (new OffcanvasCartPage())->assign(['cart' => new Cart(Uuid::randomHex())]),
            Generator::generateChannelContext(),
        );
    }
}

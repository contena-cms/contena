<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Confirm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPage;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;

/**
 * @internal
 */
#[CoversClass(CheckoutConfirmPageLoadedHook::class)]
class CheckoutConfirmPageLoadedHookTest extends TestCase
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

    private function createHook(): CheckoutConfirmPageLoadedHook
    {
        return new CheckoutConfirmPageLoadedHook(
            (new CheckoutConfirmPage())->assign(['cart' => new Cart(Uuid::randomHex())]),
            Generator::generateChannelContext(),
        );
    }
}

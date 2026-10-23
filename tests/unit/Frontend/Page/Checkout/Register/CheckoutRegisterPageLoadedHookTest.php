<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Register;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPage;
use Contena\Frontend\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;

/**
 * @internal
 */
#[CoversClass(CheckoutRegisterPageLoadedHook::class)]
class CheckoutRegisterPageLoadedHookTest extends TestCase
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

    private function createHook(): CheckoutRegisterPageLoadedHook
    {
        return new CheckoutRegisterPageLoadedHook(
            (new CheckoutRegisterPage())->assign(['cart' => new Cart(Uuid::randomHex())]),
            Generator::generateChannelContext(),
        );
    }
}

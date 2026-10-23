<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook;

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

}

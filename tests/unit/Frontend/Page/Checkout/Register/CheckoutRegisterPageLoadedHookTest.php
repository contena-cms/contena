<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Checkout\Register;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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

}

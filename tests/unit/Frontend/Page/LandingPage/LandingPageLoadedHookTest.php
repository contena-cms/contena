<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\LandingPage\LandingPage;
use Contena\Frontend\Page\LandingPage\LandingPageLoadedHook;

/**
 * @internal
 */
#[CoversClass(LandingPageLoadedHook::class)]
class LandingPageLoadedHookTest extends TestCase
{
    public function testLandingPageLoadedHook(): void
    {
        $page = new LandingPage();
        $context = Generator::generateChannelContext();

        $hook = new LandingPageLoadedHook($page, $context);
        static::assertSame('landing-page-loaded', $hook->getName());
        static::assertSame($page, $hook->getPage());
    }
}

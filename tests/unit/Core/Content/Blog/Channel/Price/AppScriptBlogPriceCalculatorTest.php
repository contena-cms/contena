<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Price\AppScriptBlogPriceCalculator;
use Contena\Core\Content\Blog\Channel\Price\BlogPriceCalculator;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(AppScriptBlogPriceCalculator::class)]
class AppScriptBlogPriceCalculatorTest extends TestCase
{
    public function testHookWillBeExecuted(): void
    {
        $blog1 = new ChannelBlogEntity();
        $blog1->setId('blog-1');
        $blog2 = new ChannelBlogEntity();
        $blog2->setId('blog-2');

        $blogs = [
            $blog1,
            $blog2,
        ];

        $executor = $this->createMock(ScriptExecutor::class);
        $executor->expects($this->once())->method('execute');

        $decorated = $this->createMock(BlogPriceCalculator::class);
        $decorated->expects($this->once())->method('calculate')->with($blogs);

        $calculator = new AppScriptBlogPriceCalculator($decorated, $executor, static::createStub(ScriptPriceStubs::class));

        $calculator->calculate($blogs, static::createStub(ChannelContext::class));
    }
}

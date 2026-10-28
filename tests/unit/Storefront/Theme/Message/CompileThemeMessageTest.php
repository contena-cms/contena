<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Storefront\Theme\Message\CompileThemeMessage;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CompileThemeMessage::class)]
class CompileThemeMessageTest extends TestCase
{
    public function testStruct(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::SALES_CHANNEL, $themeId, true, $context);

        static::assertSame($themeId, $message->getThemeId());
        static::assertSame(TestDefaults::SALES_CHANNEL, $message->getSalesChannelId());
        static::assertTrue($message->isWithAssets());
        static::assertSame($context, $message->getContext());
        static::assertTrue($message->isNotifyOnCompletion());
    }
}

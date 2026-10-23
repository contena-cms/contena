<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Theme\Message\CompileThemeMessage;

/**
 * @internal
 */
#[CoversClass(CompileThemeMessage::class)]
class CompileThemeMessageTest extends TestCase
{
    public function testStruct(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $message = new CompileThemeMessage(TestDefaults::CHANNEL, $themeId, true, $context);

        static::assertSame($themeId, $message->getThemeId());
        static::assertSame(TestDefaults::CHANNEL, $message->getChannelId());
        static::assertTrue($message->isWithAssets());
        static::assertSame($context, $message->getContext());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Frontend\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[CoversClass(DeleteThemeFilesMessage::class)]
class DeleteThemeFilesMessageTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStruct(): void
    {
        $message = new DeleteThemeFilesMessage('path', 'channel', 'theme');

        static::assertSame('path', $message->getThemePath());
        static::assertSame('channel', $message->getChannelId());
        static::assertSame('theme', $message->getThemeId());
    }
}

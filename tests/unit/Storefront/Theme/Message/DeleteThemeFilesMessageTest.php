<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Storefront\Theme\Message\DeleteThemeFilesMessage;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DeleteThemeFilesMessage::class)]
class DeleteThemeFilesMessageTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStruct(): void
    {
        $message = new DeleteThemeFilesMessage('path', 'salesChannel', 'theme');

        static::assertSame('path', $message->getThemePath());
        static::assertSame('salesChannel', $message->getSalesChannelId());
        static::assertSame('theme', $message->getThemeId());
    }
}

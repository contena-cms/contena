<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
#[CoversClass(MediaFileExtensionWhitelistEvent::class)]
class MediaFileExtensionWhitelistEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], $context);

        static::assertSame($context, $event->getContext());
    }
}

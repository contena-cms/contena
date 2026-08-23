<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Event\ThemeConfigChangedEvent;

/**
 * @internal
 */
#[CoversClass(ThemeConfigChangedEvent::class)]
class ThemeConfigChangedEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new ThemeConfigChangedEvent(Uuid::randomHex(), [], $context);

        static::assertSame($context, $event->getContext());
    }
}

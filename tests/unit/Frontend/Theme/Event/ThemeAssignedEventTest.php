<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Frontend\Theme\Event\ThemeAssignedEvent;

/**
 * @internal
 */
#[CoversClass(ThemeAssignedEvent::class)]
class ThemeAssignedEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new ThemeAssignedEvent(Uuid::randomHex(), Uuid::randomHex(), $context);

        static::assertSame($context, $event->getContext());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\Event;

use Contena\Core\Framework\Plugin\Context\ActivateContext;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent;
use Contena\Core\Framework\Plugin\PluginEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PluginPostDeactivationFailedEvent::class)]
class PluginPostDeactivationFailedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $activateContext = $this->createMock(ActivateContext::class);
        $exception = new \Exception('failed');
        $event = new PluginPostDeactivationFailedEvent(
            new PluginEntity(),
            $activateContext,
            $exception
        );
        static::assertSame($activateContext, $event->getContext());
        static::assertSame($exception, $event->getException());
    }
}

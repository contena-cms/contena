<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Consent\ConsentScope;
use Contena\Core\System\Consent\Event\ConsentAcceptedEvent;

/**
 * @internal
 */
#[CoversClass(ConsentAcceptedEvent::class)]
class ConsentAcceptedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ConsentAcceptedEvent(
            'my-consent',
            ConsentScope\AdminUser::NAME,
            'consent-identifier',
            'user-123',
            '2026-02-01',
        );

        static::assertSame('my-consent', $event->consentName);
        static::assertSame(ConsentScope\AdminUser::NAME, $event->consentScope);
        static::assertSame('consent-identifier', $event->identifier);
        static::assertSame('user-123', $event->actor);
        static::assertSame('2026-02-01', $event->revision);
        static::assertSame('consent.my-consent.accepted', $event->getName());
    }
}

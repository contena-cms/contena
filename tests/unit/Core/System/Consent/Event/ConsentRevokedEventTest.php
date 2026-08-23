<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Consent\ConsentScope;
use Contena\Core\System\Consent\Event\ConsentRevokedEvent;

/**
 * @internal
 */
#[CoversClass(ConsentRevokedEvent::class)]
class ConsentRevokedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $event = new ConsentRevokedEvent(
            'my-consent',
            ConsentScope\AdminUser::NAME,
            'consent-identifier',
            'user-456'
        );

        static::assertSame('my-consent', $event->consentName);
        static::assertSame(ConsentScope\AdminUser::NAME, $event->consentScope);
        static::assertSame('consent-identifier', $event->identifier);
        static::assertSame('user-456', $event->actor);
        static::assertSame('consent.my-consent.revoked', $event->getName());
    }
}

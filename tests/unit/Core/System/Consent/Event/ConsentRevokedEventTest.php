<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
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
            'admin_user-456'
        );

        static::assertSame('my-consent', $event->consentName);
        static::assertSame(ConsentScope\AdminUser::NAME, $event->consentScope);
        static::assertSame('consent-identifier', $event->identifier);
        static::assertSame('admin_user-456', $event->actor);
        static::assertSame('consent.my-consent.revoked', $event->getName());
        static::assertSame([
            'consentName' => 'my-consent',
            'consentScope' => ConsentScope\AdminUser::NAME,
            'identifier' => 'consent-identifier',
        ], $event->getWebhookPayload());
        static::assertTrue($event->isAllowed('app-id', new AclPrivilegeCollection(['consent:my-consent:read'])));
        static::assertFalse($event->isAllowed('app-id', new AclPrivilegeCollection(['consent:other-consent:read'])));
    }
}

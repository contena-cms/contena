<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Event;

use Contena\Core\Framework\App\Event\AppDeletedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppDeletedEvent::class)]
class AppDeletedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $event = new AppDeletedEvent(
            $appId,
            $context
        );

        static::assertSame($appId, $event->getAppId());
        static::assertSame($context, $event->getContext());
        static::assertSame(AppDeletedEvent::NAME, $event->getName());
        static::assertSame(['keepUserData' => false], $event->getWebhookPayload());
    }

    public function testIsAllowed(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $event = new AppDeletedEvent(
            $appId,
            $context
        );

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }
}

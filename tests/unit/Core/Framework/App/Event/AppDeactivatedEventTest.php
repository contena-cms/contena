<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Event;

use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\App\Event\AppDeactivatedEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppDeactivatedEvent::class)]
class AppDeactivatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $app = new AppEntity();
        $context = Context::createDefaultContext();
        $event = new AppDeactivatedEvent(
            $app,
            $context
        );

        static::assertSame($app, $event->getApp());
        static::assertSame($context, $event->getContext());
        static::assertSame(AppDeactivatedEvent::NAME, $event->getName());
        static::assertSame([], $event->getWebhookPayload());
    }

    public function testIsAllowed(): void
    {
        $appId = Uuid::randomHex();
        $app = new AppEntity()
            ->assign(['id' => $appId]);
        $context = Context::createDefaultContext();
        $event = new AppDeactivatedEvent(
            $app,
            $context
        );

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }
}

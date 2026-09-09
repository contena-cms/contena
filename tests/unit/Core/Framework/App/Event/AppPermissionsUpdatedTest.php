<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Event;

use Contena\Core\Framework\App\Event\AppPermissionsUpdated;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppPermissionsUpdated::class)]
class AppPermissionsUpdatedTest extends TestCase
{
    public function testAccessors(): void
    {
        $appId = Uuid::randomHex();
        $permissions = ['customer:read', 'product:read', 'product:update'];
        $context = Context::createDefaultContext();

        $event = new AppPermissionsUpdated($appId, $permissions, $context);

        static::assertSame($appId, $event->appId);
        static::assertSame($permissions, $event->permissions);
        static::assertSame($context, $event->getContext());
    }

    public function testGetWebhookPayload(): void
    {
        $appId = Uuid::randomHex();
        $permissions = ['customer:read', 'product:read', 'product:update'];
        $context = Context::createDefaultContext();

        $event = new AppPermissionsUpdated($appId, $permissions, $context);

        static::assertSame(
            ['permissions' => $permissions],
            $event->getWebhookPayload()
        );
    }

    public function testIsAllowed(): void
    {
        $appId = Uuid::randomHex();
        $permissions = ['customer:read', 'product:read', 'product:update'];
        $context = Context::createDefaultContext();

        $event = new AppPermissionsUpdated($appId, $permissions, $context);

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed('different-app', new AclPrivilegeCollection([])));
    }
}

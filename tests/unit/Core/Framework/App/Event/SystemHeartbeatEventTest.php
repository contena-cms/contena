<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Event;

use Contena\Core\Framework\App\Event\SystemHeartbeatEvent;
use Contena\Core\Framework\Webhook\AclPrivilegeCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(SystemHeartbeatEvent::class)]
class SystemHeartbeatEventTest extends TestCase
{
    public function testIsAlwaysAllowedWithoutAnyPermissions(): void
    {
        $event = new SystemHeartbeatEvent();
        $permissions = new AclPrivilegeCollection([]);

        static::assertTrue($event->isAllowed('any-app-id', $permissions));
    }
}

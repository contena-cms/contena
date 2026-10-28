<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Service\Notification;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Notification::class)]
class NotificationTest extends TestCase
{
    public function testDelegatesNewServicesInstalledToNotificationService(): void
    {
        $repo = new StaticEntityRepository([], new NotificationDefinition());

        $notification = new Notification(new NotificationService($repo));

        $notification->newServicesInstalled();

        static::assertNotCount(0, $repo->creates);
        static::assertCount(1, $repo->creates);

        $createdNotification = $repo->creates[0][0];

        static::assertTrue(Uuid::isValid($createdNotification['id']));
        static::assertEquals('New services have been installed. Reload your administration to see what\'s new.', $createdNotification['message']);
        static::assertEquals('positive', $createdNotification['status']);
        static::assertTrue($createdNotification['adminOnly']);
        static::assertEquals(['system.plugin_maintain'], $createdNotification['requiredPrivileges']);
    }
}

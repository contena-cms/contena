<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Payment\OpenApi\Notification;

use Contena\Core\System\Payment\OpenApi\Notification\AppNotificationDeliveryTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AppNotificationDeliveryTask::class)]
final class AppNotificationDeliveryTaskTest extends TestCase
{
    public function testRunsEveryMinuteAndRecoversFromTaskFailures(): void
    {
        static::assertSame('payment.app_notification.deliver', AppNotificationDeliveryTask::getTaskName());
        static::assertSame(60, AppNotificationDeliveryTask::getDefaultInterval());
        static::assertTrue(AppNotificationDeliveryTask::shouldRescheduleOnFailure());
    }
}

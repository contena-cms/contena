<?php declare(strict_types=1);

namespace Contena\Core\Service;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
final readonly class Notification
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    public function newServicesInstalled(): void
    {
        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'positive',
                'message' => 'New services have been installed. Reload your administration to see what\'s new.',
                'adminOnly' => true,
                'requiredPrivileges' => ['system.plugin_maintain'],
            ],
            Context::createDefaultContext()
        );
    }
}

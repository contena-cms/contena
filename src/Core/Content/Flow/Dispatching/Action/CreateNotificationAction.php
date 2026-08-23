<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class CreateNotificationAction extends FlowAction
{
    final public const string ACTION_NAME = 'action.notification.create';

    private const array ALLOWED_STATUSES = ['info', 'positive', 'warning', 'critical'];

    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public static function getName(): string
    {
        return self::ACTION_NAME;
    }

    public function requirements(): array
    {
        return [];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $config = $flow->getConfig();
        $message = $config['message'] ?? null;
        if (!\is_string($message) || trim($message) === '') {
            return;
        }

        $status = $config['status'] ?? 'info';
        if (!\is_string($status) || !\in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'info';
        }

        $requiredPrivileges = $config['requiredPrivileges'] ?? [];
        if (!\is_array($requiredPrivileges)) {
            $requiredPrivileges = [];
        }

        $requiredPrivileges = array_values(array_filter(
            $requiredPrivileges,
            static fn (mixed $privilege): bool => \is_string($privilege) && trim($privilege) !== '',
        ));

        $this->notificationService->createNotification([
            'id' => Uuid::randomHex(),
            'status' => $status,
            'message' => trim($message),
            'adminOnly' => (bool) ($config['adminOnly'] ?? false),
            'requiredPrivileges' => $requiredPrivileges,
        ], $flow->getContext());
    }
}

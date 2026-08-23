<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\Message;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Theme\ThemeService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Handles a deferred theme compile that Messenger finally gave up on: notifies the user once and
 * clears the pending marker. Doing this in the handler would repeat on every retry.
 *
 * @internal
 */
class CompileThemeFailedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageFailedEvent::class => 'onWorkerMessageFailed',
        ];
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        // Act only on the terminal failure, not on the retries that precede it.
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof CompileThemeMessage || !$message->isAssign()) {
            return;
        }

        // Clear the marker so the admin stops polling, but only while it still points at the failed
        // theme (a newer request must survive).
        if ($this->systemConfigService->getString(ThemeService::CONFIG_KEY_PENDING_THEME, $message->getChannelId()) === $message->getThemeId()) {
            $this->systemConfigService->set(ThemeService::CONFIG_KEY_PENDING_THEME, '', $message->getChannelId(), false);
        }

        if ($message->getContext()->getScope() !== Context::USER_SCOPE) {
            return;
        }

        $this->notificationService->createNotification(
            [
                'id' => Uuid::randomHex(),
                'status' => 'warning',
                'message' => 'ct-theme-manager.detail.asyncCompilation.error',
                'requiredPrivileges' => [],
            ],
            $message->getContext(),
        );
    }
}

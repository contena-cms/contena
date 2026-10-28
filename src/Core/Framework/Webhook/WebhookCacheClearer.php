<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook;

use Contena\Core\Framework\App\AppEvents;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Webhook\Service\WebhookManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class WebhookCacheClearer implements EventSubscriberInterface, ResetInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly WebhookManager $manager)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppEvents::APP_WRITTEN_EVENT => 'clearWebhookCache',
        ];
    }

    /**
     * Reset can not be handled by the Dispatcher itself, as it may be in the middle of a decoration chain
     * Therefore tagging that service directly won't work
     */
    public function reset(): void
    {
        $this->clearWebhookCache();
    }

    public function clearWebhookCache(): void
    {
        $this->manager->clearInternalWebhookCache();
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Handler;

use Contena\Core\Framework\Webhook\Message\WebhookEventMessage;
use Contena\Core\Framework\Webhook\Service\WebhookDeliveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
final readonly class WebhookEventMessageHandler
{
    /**
     * @internal
     */
    public function __construct(
        private WebhookDeliveryService $webhookDeliveryService,
    ) {
    }

    public function __invoke(WebhookEventMessage $message): void
    {
        $this->webhookDeliveryService->deliver($message);
    }
}

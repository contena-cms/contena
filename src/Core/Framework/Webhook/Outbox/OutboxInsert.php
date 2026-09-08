<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Outbox;

use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Webhook\Message\WebhookEventMessage;

/**
 * Data for inserting a new outbox entry (webhook_event_log + webhook_delivery).
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class OutboxInsert
{
    public function __construct(
        public string $webhookEventId,
        public string $webhookId,
        public string $partitionKey,
        public string $serializedMessage,
    ) {
    }

    public static function fromMessage(WebhookEventMessage $message): self
    {
        return new self(
            $message->getWebhookEventId(),
            $message->getWebhookId(),
            Hasher::hashBinary($message->getPartitionKey(), 'xxh128'),
            serialize($message),
        );
    }
}

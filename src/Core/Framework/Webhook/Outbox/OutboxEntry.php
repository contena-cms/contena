<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Outbox;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class OutboxEntry
{
    public function __construct(
        public string $webhookEventId,
        public int $sequence,
        public int $executionCount,
        public string $deliveryStatus,
        public ?string $serializedWebhookMessage = null,
    ) {
    }
}

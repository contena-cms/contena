<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Transport;

use Contena\Core\Framework\Webhook\Health\WebhookHealthTick;
use Contena\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @internal
 *
 * Constructor must not eagerly resolve the receiver service — Symfony's transport factory
 * iteration would re-enter itself. Defer it until {@see createTransport()}.
 *
 * @implements TransportFactoryInterface<WebhookTransport>
 */
class WebhookTransportFactory implements TransportFactoryInterface
{
    /**
     * @param \Closure(): MySQLWebhookReceiver $receiverLocator
     */
    public function __construct(
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly \Closure $receiverLocator,
        private readonly WebhookHealthTick $healthTick,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        return new WebhookTransport(
            $this->webhookOutboxStore,
            ($this->receiverLocator)(),
            $this->healthTick,
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return $dsn === 'contena-webhook://default';
    }
}

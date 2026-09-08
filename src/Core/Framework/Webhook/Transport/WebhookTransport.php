<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Transport;

use Contena\Core\Framework\Webhook\Health\WebhookHealthTick;
use Contena\Core\Framework\Webhook\Message\HeldDeliveryStamp;
use Contena\Core\Framework\Webhook\Message\WebhookEventMessage;
use Contena\Core\Framework\Webhook\Outbox\OutboxInsert;
use Contena\Core\Framework\Webhook\Outbox\WebhookOutboxStore;
use Contena\Core\Framework\Webhook\WebhookException;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\TransportException;
use Symfony\Component\Messenger\Transport\Receiver\KeepaliveReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Persists webhook deliveries to the outbox, which doubles as the queue consumed by
 * {@see MySQLWebhookReceiver}.
 *
 * @internal
 */
class WebhookTransport implements TransportInterface, KeepaliveReceiverInterface
{
    public function __construct(
        private readonly WebhookOutboxStore $webhookOutboxStore,
        private readonly MySQLWebhookReceiver $receiver,
        private readonly WebhookHealthTick $healthTick,
    ) {
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        if (!$message instanceof WebhookEventMessage) {
            throw WebhookException::unsupportedMessage($message::class);
        }

        try {
            $insert = OutboxInsert::fromMessage($message);
            if ($envelope->last(HeldDeliveryStamp::class) !== null) {
                $this->webhookOutboxStore->recordHeldOutboxEntry($insert);
            } else {
                $this->webhookOutboxStore->recordOutboxEntry($insert);
            }
        } catch (DBALException $e) {
            /** @phpstan-ignore contena.domainException (Symfony Messenger's worker contract requires TransportException for transport-layer failures.) */
            throw new TransportException($e->getMessage(), 0, $e);
        }

        return $envelope;
    }

    public function get(): iterable
    {
        // Release due health work before the receiver claims deliveries.
        $this->healthTick->run();

        return $this->receiver->get();
    }

    public function ack(Envelope $envelope): void
    {
        $this->receiver->ack($envelope);
    }

    public function reject(Envelope $envelope): void
    {
        $this->receiver->reject($envelope);
    }

    public function keepalive(Envelope $envelope, ?int $seconds = null): void
    {
        $this->receiver->keepalive($envelope, $seconds);
    }
}

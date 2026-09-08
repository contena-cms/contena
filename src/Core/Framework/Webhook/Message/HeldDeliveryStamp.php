<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Message;

use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Marks a delivery for paused outbox persistence.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
final class HeldDeliveryStamp implements StampInterface
{
}

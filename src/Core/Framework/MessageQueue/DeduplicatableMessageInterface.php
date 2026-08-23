<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue;

/**
 * Implement on messages that can safely be deduplicated (has to be implemented in the middleware/transport).
 * It should prevent the same message from being processed multiple times during some time window.
 *
 * The returned string MUST:
 *   - identical for semantically identical messages
 *   - not exceed 64 ASCII characters
 *   - should not include message types or class names - that will be accounted for by the middleware/transport
 */
interface DeduplicatableMessageInterface
{
    /**
     * @return string|null - returns deduplicationId unique for the message type, use null if message can't be deduplicated
     */
    public function deduplicationId(): ?string;
}

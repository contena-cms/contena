<?php declare(strict_types=1);

namespace Contena\Core\Framework\Webhook\Outbox;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class StreamLease
{
    public function __construct(
        public string $partitionKey,
        public string $workerId,
        public \DateTimeImmutable $acquiredAt,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}

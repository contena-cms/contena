<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Stats\Entity;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MessageStatsEntity extends Struct
{
    public function __construct(
        public readonly int $totalMessagesProcessed,
        public readonly \DateTimeInterface $processedSince,
        public readonly float $averageTimeInQueue,
        public readonly MessageTypeStatsCollection $messageTypeStats,
    ) {
    }
}

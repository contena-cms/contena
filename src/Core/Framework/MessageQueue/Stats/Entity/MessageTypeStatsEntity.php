<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Stats\Entity;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
class MessageTypeStatsEntity extends Struct
{
    public function __construct(
        public readonly string $type,
        public readonly int $count,
    ) {
    }
}

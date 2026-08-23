<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Stats\Entity;

use Contena\Core\Framework\Struct\Struct;

/**
 * @internal
 */
class MessageStatsResponseEntity extends Struct
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?MessageStatsEntity $stats = null,
    ) {
    }
}

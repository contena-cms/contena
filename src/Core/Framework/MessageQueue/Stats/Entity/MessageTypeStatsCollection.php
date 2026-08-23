<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Stats\Entity;

use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<MessageTypeStatsEntity>
 */
class MessageTypeStatsCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return MessageTypeStatsEntity::class;
    }
}

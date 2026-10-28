<?php declare(strict_types=1);

namespace Contena\Core\Framework\MessageQueue\Stats\Entity;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<MessageTypeStatsEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class MessageTypeStatsCollection extends Collection
{
    protected function getExpectedClass(): ?string
    {
        return MessageTypeStatsEntity::class;
    }
}

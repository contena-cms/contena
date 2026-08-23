<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\Aware\MessageAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Event\FlowEventAware;

class MessageStorer extends FlowStorer
{
    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof MessageAware || isset($stored[MessageAware::MESSAGE])) {
            return $stored;
        }

        $stored[MessageAware::MESSAGE] = \serialize($event->getMessage());

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(MessageAware::MESSAGE)) {
            return;
        }

        /** @phpstan-ignore contena.unserializeUsage */
        $mail = \unserialize($storable->getStore(MessageAware::MESSAGE));

        $storable->setData(MessageAware::MESSAGE, $mail);
    }
}

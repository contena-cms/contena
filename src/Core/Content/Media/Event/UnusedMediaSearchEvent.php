<?php declare(strict_types=1);

namespace Contena\Core\Content\Media\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

class UnusedMediaSearchEvent extends Event implements ContenaEvent
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private array $ids,
        private readonly Context $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Specify that some IDs should NOT be deleted, they are in fact used.
     *
     * @param array<string> $ids
     */
    public function markAsUsed(array $ids): void
    {
        $this->ids = array_values(array_diff($this->ids, $ids));
    }

    /**
     * @return list<string> $ids
     */
    public function getUnusedIds(): array
    {
        return $this->ids;
    }
}

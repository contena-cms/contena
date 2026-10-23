<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class BlogTypeChangedEvent extends Event implements ContenaEvent
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        private readonly array $ids,
        private readonly string $type,
        private readonly Context $context
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

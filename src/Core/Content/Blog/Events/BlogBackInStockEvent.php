<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched with the ids of blogs whose `available` flag changed from false to true.
 *
 * @codeCoverageIgnore
 */
class BlogBackInStockEvent extends Event implements ContenaEvent, BlogChangedEventInterface
{
    /**
     * @param list<string> $ids
     */
    public function __construct(
        protected array $ids,
        protected Context $context
    ) {
    }

    /**
     * @return list<string>
     */
    public function getIds(): array
    {
        return $this->ids;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

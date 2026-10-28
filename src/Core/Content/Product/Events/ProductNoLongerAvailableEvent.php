<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Events;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched with the ids of products whose `available` flag changed from true to false.
 * Products which became available are dispatched with the `ProductBackInStockEvent`.
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductNoLongerAvailableEvent extends Event implements ContenaEvent, ProductChangedEventInterface
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

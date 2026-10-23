<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class ContainerItem extends LineItem
{
    /**
     * @param array<LineItem> $items
     */
    public function __construct(array $items = [])
    {
        parent::__construct(Uuid::randomHex(), LineItem::CONTAINER_LINE_ITEM);

        $this->children = new LineItemCollection($items);

        $this->removable = true;
        $this->good = true;
    }
}

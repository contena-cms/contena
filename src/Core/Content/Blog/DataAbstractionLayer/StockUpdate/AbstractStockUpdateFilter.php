<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\DataAbstractionLayer\StockUpdate;

use Contena\Core\Framework\Context;

abstract class AbstractStockUpdateFilter
{
    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    abstract public function filter(array $ids, Context $context): array;
}

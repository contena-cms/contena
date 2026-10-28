<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractStockUpdateFilter
{
    /**
     * @param list<string> $ids
     *
     * @return list<string>
     */
    abstract public function filter(array $ids, Context $context): array;
}

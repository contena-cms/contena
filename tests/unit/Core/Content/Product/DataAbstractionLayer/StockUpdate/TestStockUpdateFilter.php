<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\DataAbstractionLayer\StockUpdate;

use Contena\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Contena\Core\Framework\Context;

/**
 * @internal
 */
class TestStockUpdateFilter extends AbstractStockUpdateFilter
{
    /**
     * @param list<string> $ids
     */
    public function __construct(private readonly array $ids)
    {
    }

    public function filter(array $ids, Context $context): array
    {
        return \array_values(\array_diff($ids, $this->ids));
    }
}

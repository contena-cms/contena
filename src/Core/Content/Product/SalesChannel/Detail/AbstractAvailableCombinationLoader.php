<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Detail;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractAvailableCombinationLoader
{
    abstract public function getDecorated(): AbstractAvailableCombinationLoader;

    abstract public function loadCombinations(string $productId, SalesChannelContext $salesChannelContext): AvailableCombinationResult;
}

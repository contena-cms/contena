<?php declare(strict_types=1);

namespace Contena\Core\Content\Product;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractProductMaxPurchaseCalculator
{
    abstract public function getDecorated(): AbstractProductMaxPurchaseCalculator;

    abstract public function calculate(Entity $product, SalesChannelContext $context): int;
}

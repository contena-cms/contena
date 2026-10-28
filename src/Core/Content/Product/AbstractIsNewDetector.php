<?php declare(strict_types=1);

namespace Contena\Core\Content\Product;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
abstract class AbstractIsNewDetector
{
    abstract public function getDecorated(): AbstractIsNewDetector;

    abstract public function isNew(Entity $product, SalesChannelContext $context): bool;
}

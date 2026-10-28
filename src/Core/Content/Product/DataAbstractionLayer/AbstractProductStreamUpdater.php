<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\DataAbstractionLayer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractProductStreamUpdater extends EntityIndexer
{
    /**
     * @param array<string> $ids
     */
    abstract public function updateProducts(array $ids, Context $context): void;
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Product;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractProductVariationBuilder
{
    abstract public function getDecorated(): AbstractProductVariationBuilder;

    abstract public function build(Entity $product): void;
}

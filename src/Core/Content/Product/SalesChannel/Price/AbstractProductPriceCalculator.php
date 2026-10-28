<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Price;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\Service\ResetInterface;

#[Package('inventory')]
abstract class AbstractProductPriceCalculator implements ResetInterface
{
    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    abstract public function getDecorated(): AbstractProductPriceCalculator;

    /**
     * @param Entity[] $products
     */
    abstract public function calculate(iterable $products, SalesChannelContext $context): void;
}

<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Product\Configurator;

use Contena\Core\Content\Product\SalesChannel\Detail\AvailableCombinationResult;
use Contena\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class ProductPageConfiguratorLoader extends ProductConfiguratorLoader
{
    /**
     * @internal
     */
    public function __construct(private readonly ProductConfiguratorLoader $loader)
    {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(SalesChannelProductEntity $product, SalesChannelContext $context): PropertyGroupCollection
    {
        return $this->loader->load($product, $context);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function loadFromCombinations(
        SalesChannelProductEntity $product,
        AvailableCombinationResult $combinations,
        SalesChannelContext $context
    ): PropertyGroupCollection {
        return $this->loader->loadFromCombinations($product, $combinations, $context);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Detail;

use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * Adapts minimal product data to the existing ProductConfiguratorLoader API without changing its public contract.
 *
 * @internal
 */
#[Package('inventory')]
final class PartialProductConfiguratorLoader
{
    public function __construct(
        private readonly ProductConfiguratorLoader $productConfiguratorLoader,
    ) {
    }

    public function load(
        ProductConfiguratorProductData $productData,
        SalesChannelContext $context,
    ): PropertyGroupCollection {
        $product = new SalesChannelProductEntity();
        $product->setId($productData->id);
        $product->setParentId($productData->parentId);
        $product->setOptionIds($productData->optionIds);
        $product->setVariantListingConfig($productData->variantListingConfig);

        return $this->productConfiguratorLoader->load($product, $context);
    }
}

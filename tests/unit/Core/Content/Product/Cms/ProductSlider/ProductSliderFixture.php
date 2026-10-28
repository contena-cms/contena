<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Cms\ProductSlider;

use Contena\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds the products, slot and resolver context the product slider processor tests share. The slot takes
 * the field configuration the test assembled, so no state lives here.
 *
 * @internal
 */
#[Package('discovery')]
final class ProductSliderFixture
{
    public static function getProducts(): ProductCollection
    {
        $product = (new ProductEntity())->assign([
            'id' => 'product-1',
            '_uniqueIdentifier' => 'product-1',
            'isCloseout' => false,
            'variantListingConfig' => new VariantListingConfig(
                false,
                'product-1',
                null
            ),
            'stock' => 12,
        ]);

        $product2 = (new ProductEntity())->assign([
            'id' => 'product-2',
            '_uniqueIdentifier' => 'product-2',
            'isCloseout' => true,
            'stock' => 0,
        ]);

        return new ProductCollection([$product, $product2]);
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public static function getEntitySearchResult(ProductCollection $products): EntitySearchResult
    {
        return new EntitySearchResult(
            'product',
            $products->count(),
            $products,
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }


}

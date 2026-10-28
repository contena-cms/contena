<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Content\Category\CategoryHydrator;
use Contena\Core\Content\Media\MediaHydrator;
use Contena\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingHydrator;
use Contena\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingHydrator;
use Contena\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsHydrator;
use Contena\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetHydrator;
use Contena\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryHydrator;
use Contena\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerHydrator;
use Contena\Core\Content\Product\Aggregate\ProductMedia\ProductMediaHydrator;
use Contena\Core\Content\Product\Aggregate\ProductPrice\ProductPriceHydrator;
use Contena\Core\Content\Product\Aggregate\ProductReview\ProductReviewHydrator;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigHydrator;
use Contena\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldHydrator;
use Contena\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordHydrator;
use Contena\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityHydrator;
use Contena\Core\Content\Product\ProductHydrator;
use Contena\Core\Content\Product\SalesChannel\Sorting\ProductSortingHydrator;
use Contena\Core\Content\ProductExport\ProductExportHydrator;
use Contena\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterHydrator;
use Contena\Core\Content\ProductStream\ProductStreamHydrator;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionHydrator;
use Contena\Core\Content\Property\PropertyGroupHydrator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CategoryHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductConfiguratorSettingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductPriceHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSearchKeywordHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductKeywordDictionaryHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductReviewHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductManufacturerHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductMediaHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductCrossSellingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductCrossSellingAssignedProductsHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductFeatureSetHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSortingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSearchConfigHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductSearchConfigFieldHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductVisibilityHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductStreamHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductStreamFilterHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ProductExportHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(PropertyGroupHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(PropertyGroupOptionHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(MediaHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);
};

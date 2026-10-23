<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Content\Category\CategoryHydrator;
use Contena\Core\Content\Media\MediaHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogFeatureSet\BlogFeatureSetHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityHydrator;
use Contena\Core\Content\Blog\BlogHydrator;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingHydrator;
use Contena\Core\Content\BlogExport\BlogExportHydrator;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterHydrator;
use Contena\Core\Content\ContentStream\ContentStreamHydrator;
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

    $services->set(BlogHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogConfiguratorSettingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogPriceHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogSearchKeywordHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogKeywordDictionaryHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogReviewHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogManufacturerHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogMediaHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogCrossSellingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogCrossSellingAssignedBlogsHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogFeatureSetHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogSortingHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogSearchConfigHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogSearchConfigFieldHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogVisibilityHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ContentStreamHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(ContentStreamFilterHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(BlogExportHydrator::class)
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

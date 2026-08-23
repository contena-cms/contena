<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Content\Blog\Aggregate\BlogKeywordDictionary\BlogKeywordDictionaryHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogSearchKeyword\BlogSearchKeywordHydrator;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityHydrator;
use Contena\Core\Content\Blog\BlogHydrator;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingHydrator;
use Contena\Core\Content\Category\CategoryHydrator;
use Contena\Core\Content\Media\MediaHydrator;
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

    $services->set(BlogMediaHydrator::class)
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

    $services->set(MediaHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);
};

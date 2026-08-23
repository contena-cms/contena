<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Contena\Core\System\Country\Channel\AbstractCountryRoute;
use Contena\Core\System\Country\Channel\ChannelCountryDefinition;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\CountryDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CountryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelCountryDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(CountryTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CountryRoute::class)
        ->public()
        ->args([
            service('channel.country.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);
    $services->alias(AbstractCountryRoute::class, CountryRoute::class);
};

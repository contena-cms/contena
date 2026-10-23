<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\Country\Aggregate\CountryState\Channel\ChannelCountryStateDefinition;
use Contena\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Contena\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\Country\Channel\CountryRoute;
use Contena\Core\System\Country\Channel\CountryStateRoute;
use Contena\Core\System\Country\Channel\ChannelCountryDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CountryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelCountryDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(CountryStateDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelCountryStateDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(CountryStateTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CountryTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CountryRoute::class)
        ->public()
        ->args([
            service('channel.country.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CountryStateRoute::class)
        ->public()
        ->args([
            service('country_state.repository'),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);
};

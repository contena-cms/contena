<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\System\Language\CachedLanguageLoader;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Language\Channel\ChannelLanguageDefinition;
use Contena\Core\System\Language\Channel\LanguageRoute;
use Contena\Core\System\Language\ChannelLanguageLoader;
use Contena\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Contena\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfigSerializer;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Language\LanguageLoader;
use Contena\Core\System\Language\LanguageValidator;
use Contena\Core\System\Language\TranslationValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LanguageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelLanguageDefinition::class)
        ->tag('contena.channel.entity.definition');

    $services->set(LanguageRoute::class)
        ->public()
        ->args([
            service('channel.language.repository'),
            service(CacheTagCollector::class),
        ]);

    $services->alias(AbstractLanguageRoute::class, LanguageRoute::class);

    $services->set(LanguageDataLoader::class)
        ->args([service(AbstractLanguageRoute::class)])
        ->tag('content_system.data_loader');

    $services->set(LanguageLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(LanguageValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ChannelLanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CachedLanguageLoader::class)
        ->decorate(LanguageLoader::class)
        ->args([
            service(CachedLanguageLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TranslationValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');
};

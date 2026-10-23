<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Language\CachedLanguageLoader;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\Language\LanguageExceptionHandler;
use Contena\Core\System\Language\LanguageLoader;
use Contena\Core\System\Language\LanguageValidator;
use Contena\Core\System\Language\Rule\LanguageRule;
use Contena\Core\System\Language\Channel\LanguageRoute;
use Contena\Core\System\Language\Channel\ChannelLanguageDefinition;
use Contena\Core\System\Language\ChannelLanguageLoader;
use Contena\Core\System\Language\TranslationValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LanguageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelLanguageDefinition::class)
        ->tag('contena.channel.entity.definition');

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

    $services->set(LanguageExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(LanguageRoute::class)
        ->public()
        ->args([
            service('channel.language.repository'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LanguageRule::class)
        ->tag('contena.rule.definition');
};

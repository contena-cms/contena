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
use Contena\Core\System\Language\SalesChannel\LanguageRoute;
use Contena\Core\System\Language\SalesChannel\SalesChannelLanguageDefinition;
use Contena\Core\System\Language\SalesChannelLanguageLoader;
use Contena\Core\System\Language\TranslationValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LanguageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelLanguageDefinition::class)
        ->tag('contena.sales_channel.entity.definition');

    $services->set(LanguageValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LanguageLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SalesChannelLanguageLoader::class)
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
            service('sales_channel.language.repository'),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(LanguageRule::class)
        ->tag('contena.rule.definition');

    // Content System
    $services->alias(Contena\Core\System\Language\SalesChannel\AbstractLanguageRoute::class, Contena\Core\System\Language\SalesChannel\LanguageRoute::class);

    $services->set(Contena\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader::class)
        ->args([
            service(Contena\Core\System\Language\SalesChannel\AbstractLanguageRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(Contena\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');
};

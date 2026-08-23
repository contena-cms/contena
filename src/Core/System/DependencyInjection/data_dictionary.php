<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Monolog\Logger;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Contena\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItem\DataDictionaryItemDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryItemTranslation\DataDictionaryItemTranslationDefinition;
use Contena\Core\System\DataDictionary\Aggregate\DataDictionaryTranslation\DataDictionaryTranslationDefinition;
use Contena\Core\System\DataDictionary\CachedDataDictionaryLoader;
use Contena\Core\System\DataDictionary\DataAbstractionLayer\DataDictionaryItemIndexer;
use Contena\Core\System\DataDictionary\DataDictionaryAuditSubscriber;
use Contena\Core\System\DataDictionary\DataDictionaryDefinition;
use Contena\Core\System\DataDictionary\DataDictionaryLoader;
use Contena\Core\System\DataDictionary\DataDictionaryLoaderInterface;
use Contena\Core\System\DataDictionary\DataDictionaryWriteValidator;
use Contena\Core\System\User\Validator\UserGenderValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DataDictionaryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(DataDictionaryTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(DataDictionaryItemDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(DataDictionaryItemTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(DataDictionaryItemIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('data_dictionary_item.repository'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
        ])
        ->tag('contena.entity_indexer');

    $services->set(DataDictionaryLoaderInterface::class, DataDictionaryLoader::class)
        ->args([
            service('data_dictionary.repository'),
        ]);

    $services->set(CachedDataDictionaryLoader::class)
        ->decorate(DataDictionaryLoaderInterface::class)
        ->args([
            service(CachedDataDictionaryLoader::class . '.inner'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DataDictionaryWriteValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserGenderValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('data_dictionary.audit_logger', Logger::class)
        ->args(['data_dictionary_audit'])
        ->call('pushHandler', [service(DoctrineSQLHandler::class)]);

    $services->set(DataDictionaryAuditSubscriber::class)
        ->args([
            service('data_dictionary.audit_logger'),
        ])
        ->tag('kernel.event_subscriber');
};

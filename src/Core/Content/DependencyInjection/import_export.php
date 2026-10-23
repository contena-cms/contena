<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileDefinition;
use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogDefinition;
use Contena\Core\Content\ImportExport\Command\DeleteExpiredFilesCommand;
use Contena\Core\Content\ImportExport\Command\ImportEntityCommand;
use Contena\Core\Content\ImportExport\Controller\ImportExportActionController;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\CountrySerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\UserSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\EntitySerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\LanguageSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\MediaSerializerSubscriber;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\BlogSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\PromotionIndividualCodeSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\CustomFieldsSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\PriceSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\ToOneSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\TranslationsSerializer;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Contena\Core\Content\ImportExport\DataAbstractionLayer\SystemDefaultValidator;
use Contena\Core\Content\ImportExport\Event\Subscriber\CategoryCriteriaSubscriber;
use Contena\Core\Content\ImportExport\Event\Subscriber\UserNumberRangeSubscriber;
use Contena\Core\Content\ImportExport\Event\Subscriber\FileDeletedSubscriber;
use Contena\Core\Content\ImportExport\Event\Subscriber\BlogCategoryPathsSubscriber;
use Contena\Core\Content\ImportExport\Event\Subscriber\BlogCriteriaSubscriber;
use Contena\Core\Content\ImportExport\Event\Subscriber\BlogVariantsSubscriber;
use Contena\Core\Content\ImportExport\ImportExportFactory;
use Contena\Core\Content\ImportExport\ImportExportProfileDefinition;
use Contena\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Contena\Core\Content\ImportExport\Message\DeleteFileHandler;
use Contena\Core\Content\ImportExport\Message\ImportExportHandler;
use Contena\Core\Content\ImportExport\Processing\Pipe\PipeFactory;
use Contena\Core\Content\ImportExport\Processing\Reader\CsvReaderFactory;
use Contena\Core\Content\ImportExport\Processing\Writer\CsvFileWriterFactory;
use Contena\Core\Content\ImportExport\ScheduledTask\CleanupImportExportFileTask;
use Contena\Core\Content\ImportExport\ScheduledTask\CleanupImportExportFileTaskHandler;
use Contena\Core\Content\ImportExport\Service\UserNumberRangeConfigService;
use Contena\Core\Content\ImportExport\Service\UserNumberRangePatternMatcher;
use Contena\Core\Content\ImportExport\Service\DeleteExpiredFilesService;
use Contena\Core\Content\ImportExport\Service\DownloadService;
use Contena\Core\Content\ImportExport\Service\FileService;
use Contena\Core\Content\ImportExport\Service\ImportExportService;
use Contena\Core\Content\ImportExport\Service\MappingService;
use Contena\Core\Content\ImportExport\Service\SupportedFeaturesService;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Blog\BlogTypeRegistry;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer as DalCustomFieldsSerializer;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('import_export.supported_entities', ['user', 'blog'])
        ->set('import_export.supported_file_types', ['text/csv'])
        ->set('import_export.read_buffer_size', 100)
        ->set('import_export.write_buffer_size', 100)
        ->set('import_export.http_batch_size', 100);

    $services = $containerConfigurator->services();

    $services->set(ImportExportProfileDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ImportExportLogDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ImportExportFileDefinition::class)
        ->tag('contena.entity.definition');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(ImportExportProfileTranslationDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(SystemDefaultValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserNumberRangeSubscriber::class)
        ->args([
            service(UserNumberRangeConfigService::class),
            service(UserNumberRangePatternMatcher::class),
            service(AbstractIncrementStorage::class),
            service('user.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserNumberRangePatternMatcher::class);

    $services->set(UserNumberRangeConfigService::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ImportExportService::class)
        ->args([
            service('import_export_log.repository'),
            service('admin_user.repository'),
            service('import_export_profile.repository'),
            service(FileService::class),
        ]);

    $services->set(MappingService::class)
        ->args([
            service(FileService::class),
            service('import_export_profile.repository'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ]);

    $services->set(FileService::class)
        ->args([
            service('contena.filesystem.private'),
            service('import_export_file.repository'),
        ]);

    $services->set(ImportExportActionController::class)
        ->public()
        ->args([
            service(SupportedFeaturesService::class),
            service(ImportExportService::class),
            service(DownloadService::class),
            service('import_export_profile.repository'),
            service(DataValidator::class),
            service(ImportExportFactory::class),
            service(DefinitionInstanceRegistry::class),
            service('messenger.default_bus'),
            service(MappingService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SupportedFeaturesService::class)
        ->args([
            param('import_export.supported_entities'),
            param('import_export.supported_file_types'),
        ]);

    $services->set(DownloadService::class)
        ->args([
            service('contena.filesystem.private'),
            service('import_export_file.repository'),
            service('logger'),
            param('contena.filesystem.private_local_download_strategy'),
            service(RateLimiter::class),
            param('contena.filesystem.private_local_path_prefix'),
            service(ClockInterface::class),
        ]);

    $services->set(PrimaryKeyResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(FieldSerializer::class),
        ]);

    $services->set(SerializerRegistry::class)
        ->args([
            tagged_iterator('contena.import_export.entity_serializer'),
            tagged_iterator('contena.import_export.field_serializer'),
        ]);

    $services->set(EntitySerializer::class)
        ->tag('contena.import_export.entity_serializer', ['priority' => -999]);

    $services->set(FieldSerializer::class)
        ->tag('contena.import_export.field_serializer', ['priority' => -999]);

    $services->set(ToOneSerializer::class)
        ->args([
            service(PrimaryKeyResolver::class),
        ])
        ->tag('contena.import_export.field_serializer', ['priority' => -500]);

    $services->set(TranslationsSerializer::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('contena.import_export.field_serializer', ['priority' => -500]);

    $services->set(PriceSerializer::class)
        ->args([
            service('currency.repository'),
        ])
        ->tag('contena.import_export.field_serializer', ['priority' => -500]);

    $services->set(CustomFieldsSerializer::class)
        ->args([
            service(DalCustomFieldsSerializer::class),
            service(CustomFieldService::class),
        ])
        ->tag('contena.import_export.field_serializer', ['priority' => -500]);

    $services->set(MediaSerializer::class)
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service('media_folder.repository'),
            service('media.repository'),
        ])
        ->tag('contena.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(MediaSerializerSubscriber::class)
        ->args([
            service(MediaSerializer::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CountrySerializer::class)
        ->args([
            service('country.repository'),
        ])
        ->tag('contena.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(LanguageSerializer::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('contena.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(UserSerializer::class)
        ->args([
            service('user_group.repository'),
            service('channel.repository'),
        ])
        ->tag('contena.import_export.entity_serializer', ['priority' => -400])
        ->tag('kernel.reset', ['method' => 'reset']);

        $services->set(BlogSerializer::class)
        ->args([
            service('blog_visibility.repository'),
            service('channel.repository'),
            service('blog_media.repository'),
        ])
        ->tag('contena.import_export.entity_serializer', ['priority' => -400]);



    $services->set(CsvReaderFactory::class)
        ->tag('contena.import_export.reader_factory');

    $services->set(CsvFileWriterFactory::class)
        ->args([
            service('contena.filesystem.private'),
        ])
        ->tag('contena.import_export.writer_factory');

    $services->set(PipeFactory::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(SerializerRegistry::class),
            service(PrimaryKeyResolver::class),
        ])
        ->tag('contena.import_export.pipe_factory');

    $services->set(ImportExportFactory::class)
        ->public()
        ->args([
            service(ImportExportService::class),
            service(DefinitionInstanceRegistry::class),
            service('contena.filesystem.private'),
            service('event_dispatcher'),
            service(Connection::class),
            service(FileService::class),
            tagged_iterator('contena.import_export.reader_factory'),
            tagged_iterator('contena.import_export.writer_factory'),
            tagged_iterator('contena.import_export.pipe_factory'),
        ]);

    $services->set(ImportExportHandler::class)
        ->public()
        ->args([
            service('messenger.default_bus'),
            service(ImportExportFactory::class),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeleteExpiredFilesService::class)
        ->args([
            service('import_export_file.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteExpiredFilesCommand::class)
        ->args([
            service(DeleteExpiredFilesService::class),
        ])
        ->tag('console.command');

    // Message handler
    $services->set(DeleteFileHandler::class)
        ->args([
            service('contena.filesystem.private'),
        ])
        ->tag('messenger.message_handler');

    // Subscriber
    $services->set(FileDeletedSubscriber::class)
        ->args([
            service('messenger.default_bus'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryCriteriaSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(BlogCategoryPathsSubscriber::class)
        ->args([
            service('category.repository'),
            service(SyncService::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(BlogCriteriaSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(BlogVariantsSubscriber::class)
        ->args([
            service(SyncService::class),
            service(Connection::class),
            service('property_group.repository'),
            service('property_group_option.repository'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ImportEntityCommand::class)
        ->args([
            service(ImportExportService::class),
            service('import_export_profile.repository'),
            service(ImportExportFactory::class),
            service(Connection::class),
            service('contena.filesystem.private'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(CleanupImportExportFileTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupImportExportFileTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(DeleteExpiredFilesService::class),
        ])
        ->tag('messenger.message_handler');
};

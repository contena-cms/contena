<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Media\Aggregate\MediaDefaultFolder\MediaDefaultFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationDefinition;
use Contena\Core\Content\Media\Aggregate\MediaFolderConfigurationMediaThumbnailSize\MediaFolderConfigurationMediaThumbnailSizeDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTag\MediaTagDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Contena\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeDefinition;
use Contena\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Contena\Core\Content\Media\Api\MediaDownloadController;
use Contena\Core\Content\Media\Api\MediaFolderController;
use Contena\Core\Content\Media\Api\MediaUploadController;
use Contena\Core\Content\Media\Api\MediaUploadV2Controller;
use Contena\Core\Content\Media\Api\MediaVideoCoverController;
use Contena\Core\Content\Media\Api\PresignedUploadController;
use Contena\Core\Content\Media\Channel\MediaRoute;
use Contena\Core\Content\Media\Commands\DeleteNotUsedMediaCommand;
use Contena\Core\Content\Media\Commands\DeleteThumbnailsCommand;
use Contena\Core\Content\Media\Commands\GenerateMediaTypesCommand;
use Contena\Core\Content\Media\Commands\GenerateThumbnailsCommand;
use Contena\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Contena\Core\Content\Media\Core\Application\AbstractMediaUrlGenerator;
use Contena\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Contena\Core\Content\Media\DataAbstractionLayer\MediaFolderConfigurationIndexer;
use Contena\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer;
use Contena\Core\Content\Media\DataAbstractionLayer\MediaIndexer;
use Contena\Core\Content\Media\File\DownloadResponseGenerator;
use Contena\Core\Content\Media\File\FileContentValidationStrategy;
use Contena\Core\Content\Media\File\FileFetcher;
use Contena\Core\Content\Media\File\FileLoader;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Content\Media\File\FileService;
use Contena\Core\Content\Media\File\FileUrlValidator;
use Contena\Core\Content\Media\File\FileUrlValidatorInterface;
use Contena\Core\Content\Media\File\SvgContentValidator;
use Contena\Core\Content\Media\File\WindowsStyleFileNameProvider;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaFolderService;
use Contena\Core\Content\Media\MediaService;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandler;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Media\Message\DeleteFileHandler;
use Contena\Core\Content\Media\Message\GenerateThumbnailsHandler;
use Contena\Core\Content\Media\Metadata\MetadataLoader;
use Contena\Core\Content\Media\Metadata\MetadataLoader\ImageMetadataLoader;
use Contena\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaHandler;
use Contena\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaTask;
use Contena\Core\Content\Media\Service\VideoCoverService;
use Contena\Core\Content\Media\Subscriber\CustomFieldsUnusedMediaSubscriber;
use Contena\Core\Content\Media\Subscriber\MediaCreationSubscriber;
use Contena\Core\Content\Media\Subscriber\MediaDeletionSubscriber;
use Contena\Core\Content\Media\Subscriber\MediaFolderConfigLoadedSubscriber;
use Contena\Core\Content\Media\Subscriber\MediaLoadedSubscriber;
use Contena\Core\Content\Media\Subscriber\MediaVisibilityRestrictionSubscriber;
use Contena\Core\Content\Media\Subscriber\VideoCoverCleanupSubscriber;
use Contena\Core\Content\Media\Subscriber\VideoCoverLoadedSubscriber;
use Contena\Core\Content\Media\Thumbnail\ExternalThumbnailCollectionNormalizer;
use Contena\Core\Content\Media\Thumbnail\ExternalThumbnailDataNormalizer;
use Contena\Core\Content\Media\Thumbnail\Processor\GdImageThumbnailProcessor;
use Contena\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Contena\Core\Content\Media\Thumbnail\ThumbnailService;
use Contena\Core\Content\Media\Thumbnail\ThumbnailSizeCalculator;
use Contena\Core\Content\Media\TypeDetector\AudioTypeDetector;
use Contena\Core\Content\Media\TypeDetector\DefaultTypeDetector;
use Contena\Core\Content\Media\TypeDetector\DocumentTypeDetector;
use Contena\Core\Content\Media\TypeDetector\ImageTypeDetector;
use Contena\Core\Content\Media\TypeDetector\SpatialObjectTypeDetector;
use Contena\Core\Content\Media\TypeDetector\TypeDetector;
use Contena\Core\Content\Media\TypeDetector\VideoTypeDetector;
use Contena\Core\Content\Media\UnusedMediaPurger;
use Contena\Core\Content\Media\Upload\MediaFileCleanupService;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Content\Media\Upload\MediaFileExtensionValidator;
use Contena\Core\Content\Media\Upload\MediaUploadService;
use Contena\Core\Content\Media\Upload\PresignedMediaUploadService;
use Contena\Core\Content\Media\Upload\PresignedUploadUrlGenerator;
use Contena\Core\Content\Media\Upload\PresignedUrlGeneratorInterface;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('contena.media.metadata.types', [
            '\Contena\Core\Content\Media\Metadata\Type\ImageMetadata',
            '\Contena\Core\Content\Media\Metadata\Type\DocumentMetadata',
            '\Contena\Core\Content\Media\Metadata\Type\VideoMetadata',
        ]);

    $services = $containerConfigurator->services();

    // region Entity definitions
    $services->set(MediaDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(MediaDefaultFolderDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaThumbnailDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaFolderDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaThumbnailSizeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaFolderConfigurationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaFolderConfigurationMediaThumbnailSizeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(MediaTagDefinition::class)
        ->tag('contena.entity.definition');
    // endregion Entity definitions

    // region Message handlers
    $services->set(GenerateThumbnailsHandler::class)
        ->args([
            service(ThumbnailService::class),
            service('media.repository'),
            service('logger'),
            param('contena.media.remote_thumbnails.enable'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeleteFileHandler::class)
        ->args([
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupCorruptedMediaHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('media.repository'),
            service(ClockInterface::class),
            service(TenantScopeContextProvider::class),
        ])
        ->tag('messenger.message_handler');
    // endregion Message handlers

    // region File Services
    $services->set(FileService::class);

    $services->set(FileFetcher::class)
        ->args([
            service(FileUrlValidatorInterface::class),
            service(FileService::class),
            param('contena.media.enable_url_upload_feature'),
            param('contena.media.enable_url_validation'),
            param('contena.media.url_upload_max_size'),
        ]);

    $services->set(FileUrlValidatorInterface::class, FileUrlValidator::class);

    $services->set(FileContentValidationStrategy::class)
        ->args([
            tagged_iterator('contena.media.file_content.validator'),
        ]);

    $services->set(SvgContentValidator::class)
        ->args([
            param('contena.media.svg.allowed_elements'),
            param('contena.media.svg.allowed_attributes'),
            param('contena.media.svg.allowed_reference_attributes'),
        ])
        ->tag('contena.media.file_content.validator');

    $services->set(FileSaver::class)
        ->public()
        ->args([
            service('media.repository'),
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
            service(FileContentValidationStrategy::class),
            service(MetadataLoader::class),
            service(TypeDetector::class),
            service('event_dispatcher'),
            service(MediaLocationBuilder::class),
            service(AbstractMediaPathStrategy::class),
            service(MediaFileCleanupService::class),
            service(MediaFileExtensionValidator::class),
            service(ClockInterface::class),
            param('contena.media.remote_thumbnails.enable'),
        ]);

    $services->set(FileLoader::class)
        ->args([
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
            service('media.repository'),
            service(Psr17Factory::class),
        ]);

    $services->set(FileNameProvider::class, WindowsStyleFileNameProvider::class)
        ->args([
            service('media.repository'),
        ]);

    $services->set(DownloadResponseGenerator::class)
        ->args([
            service('logger'),
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
            service(MediaService::class),
            param('contena.filesystem.private_local_download_strategy'),
            service(AbstractMediaUrlGenerator::class),
            service(ClockInterface::class),
            param('contena.filesystem.private_local_path_prefix'),
        ]);
    // endregion File Services

    // region Commands
    $services->set(GenerateThumbnailsCommand::class)
        ->args([
            service(ThumbnailService::class),
            service('media.repository'),
            service('media_folder.repository'),
            service('messenger.default_bus'),
            service(TenantScopeContextProvider::class),
            param('contena.media.remote_thumbnails.enable'),
        ])
        ->tag('console.command');

    $services->set(GenerateMediaTypesCommand::class)
        ->args([
            service(TypeDetector::class),
            service('media.repository'),
            service(TenantScopeContextProvider::class),
        ])
        ->tag('console.command');

    $services->set(DeleteNotUsedMediaCommand::class)
        ->share(false)
        ->args([
            service(UnusedMediaPurger::class),
            service('event_dispatcher'),
        ])
        ->tag('console.command');

    $services->set(DeleteThumbnailsCommand::class)
        ->args([
            service(Connection::class),
            service('media_thumbnail.repository'),
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
            service(TenantScopeContextProvider::class),
            param('contena.media.remote_thumbnails.enable'),
        ])
        ->tag('console.command');
    // endregion Commands

    // region Controller
    $services->set(MediaUploadController::class)
        ->public()
        ->args([
            service(MediaService::class),
            service(FileSaver::class),
            service(FileNameProvider::class),
            service(MediaDefinition::class),
            service('event_dispatcher'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(MediaDownloadController::class)
        ->public()
        ->args([
            service('media.repository'),
            service(DownloadResponseGenerator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(MediaFolderController::class)
        ->public()
        ->args([
            service(MediaFolderService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(MediaUploadV2Controller::class)
        ->public()
        ->args([
            service(MediaUploadService::class),
            service('media.repository'),
        ]);

    $services->set(MediaVideoCoverController::class)
        ->public()
        ->args([
            service(VideoCoverService::class),
        ])
        ->tag('controller.service_arguments')
        ->call('setContainer', [
            service('service_container'),
        ]);
    // endregion Controller

    // region Normalizers
    $services->set(ExternalThumbnailCollectionNormalizer::class)
        ->tag('serializer.normalizer');

    $services->set(ExternalThumbnailDataNormalizer::class)
        ->tag('serializer.normalizer');
    // endregion Normalizers

    // region Metadata
    $services->set(ImageMetadataLoader::class)
        ->tag('contena.metadata.loader');

    $services->set(MetadataLoader::class)
        ->args([
            tagged_iterator('contena.metadata.loader'),
        ]);
    // endregion Metadata

    // region TypeDetector
    $services->set(AudioTypeDetector::class)
        ->tag('contena.media_type.detector', ['priority' => 10]);

    $services->set(DefaultTypeDetector::class)
        ->tag('contena.media_type.detector', ['priority' => 0]);

    $services->set(DocumentTypeDetector::class)
        ->tag('contena.media_type.detector', ['priority' => 10]);

    $services->set(ImageTypeDetector::class)
        ->tag('contena.media_type.detector', ['priority' => 10]);

    $services->set(VideoTypeDetector::class)
        ->tag('contena.media_type.detector', ['priority' => 10]);

    $services->set(SpatialObjectTypeDetector::class)
        ->tag('contena.media_type.detector', ['priority' => 10]);

    $services->set(TypeDetector::class)
        ->args([
            tagged_iterator('contena.media_type.detector'),
        ]);
    // endregion TypeDetector

    // region Services
    $services->set(UnusedMediaPurger::class)
        ->args([
            service('media.repository'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            service(TenantScopeContextProvider::class),
        ]);

    $services->set(MediaFolderService::class)
        ->args([
            service('media.repository'),
            service('media_folder.repository'),
            service('media_folder_configuration.repository'),
        ]);

    $services->set(ThumbnailProcessorInterface::class, GdImageThumbnailProcessor::class);

    $services->set(ThumbnailService::class)
        ->args([
            service('media_thumbnail.repository'),
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
            service('media_folder.repository'),
            service('event_dispatcher'),
            service(MediaIndexer::class),
            service(ThumbnailSizeCalculator::class),
            service(Connection::class),
            service(ThumbnailProcessorInterface::class),
            service('logger'),
            param('contena.media.remote_thumbnails.enable'),
        ]);

    $services->set(MediaService::class)
        ->args([
            service('media.repository'),
            service('media_folder.repository'),
            service(FileLoader::class),
            service(FileSaver::class),
            service(FileFetcher::class),
        ]);

    $services->set(MediaUploadService::class)
        ->args([
            service('media.repository'),
            service(FileFetcher::class),
            service(FileSaver::class),
            service('event_dispatcher'),
            service('contena.media.upload.http_client'),
            service('media_thumbnail.repository'),
            service('media_thumbnail_size.repository'),
            service(FileUrlValidatorInterface::class),
            param('contena.media.enable_url_validation'),
        ]);

    $services->set(VideoCoverService::class)
        ->args([
            service('media.repository'),
        ]);

    $services->set(ThumbnailSizeCalculator::class);
    // endregion Services

    // region Testable service aliases
    $services->alias('contena.media.upload.http_client', 'http_client');
    // endregion Testable service aliases

    $services->set(PresignedUploadUrlGenerator::class)
        ->factory([PresignedUploadUrlGenerator::class, 'create'])
        ->args([
            service(AbstractMediaPathStrategy::class),
            param('contena.filesystem.public'),
            service('logger'),
            service(ClockInterface::class),
            service('contena.filesystem.s3.client')->nullOnInvalid(),
            param('contena.media.presigned_upload.expiration_minutes'),
            param('contena.media.presigned_upload.enabled'),
            param('contena.filesystem.private'),
        ]);

    $services->alias(PresignedUrlGeneratorInterface::class, PresignedUploadUrlGenerator::class);

    $services->set(MediaFileCleanupService::class)
        ->args([
            service('contena.filesystem.public'),
            service('contena.filesystem.private'),
            service(ThumbnailService::class),
            service('messenger.default_bus'),
            param('contena.media.remote_thumbnails.enable'),
        ]);

    $services->set(MediaFileExtensionListProvider::class)
        ->args([
            service('event_dispatcher'),
            param('contena.filesystem.allowed_extensions'),
            param('contena.filesystem.private_allowed_extensions'),
        ]);

    $services->set(MediaFileExtensionValidator::class)
        ->args([
            service(MediaFileExtensionListProvider::class),
        ]);

    $services->set(PresignedMediaUploadService::class)
        ->args([
            service('media.repository'),
            service(PresignedUrlGeneratorInterface::class),
            service('event_dispatcher'),
            service(TypeDetector::class),
            service(MediaFileCleanupService::class),
            service(MediaFileExtensionValidator::class),
            service(AbstractMediaPathStrategy::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(PresignedUploadController::class)
        ->public()
        ->args([
            service(PresignedMediaUploadService::class),
        ]);

    $services->set(MediaUrlPlaceholderHandlerInterface::class, MediaUrlPlaceholderHandler::class)
        ->public()
        ->args([
            service(Connection::class),
            service(AbstractMediaUrlGenerator::class),
        ]);

    // region Resolver
    $services->set(MediaIndexer::class)
        ->tag('contena.entity_indexer')
        ->args([
            service(IteratorFactory::class),
            service('media.repository'),
            service('media_thumbnail.repository'),
            service(Connection::class),
            service('event_dispatcher'),
            param('contena.media.remote_thumbnails.enable'),
        ]);

    $services->set(MediaFolderConfigurationIndexer::class)
        ->tag('contena.entity_indexer')
        ->args([
            service(IteratorFactory::class),
            service('media_folder_configuration.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(MediaFolderIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('media_folder.repository'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
        ])
        ->tag('contena.entity_indexer');
    // endregion DBAL

    // region Event handling
    $services->set(MediaFolderConfigLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(MediaDeletionSubscriber::class)
        ->args([
            service('event_dispatcher'),
            service('media_thumbnail.repository'),
            service('messenger.default_bus'),
            service(DeleteFileHandler::class),
            service(Connection::class),
            service('media.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MediaVisibilityRestrictionSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(MediaCreationSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldsUnusedMediaSubscriber::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(VideoCoverLoadedSubscriber::class)
        ->args([
            service('media.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(VideoCoverCleanupSubscriber::class)
        ->args([
            service('media.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(MediaLoadedSubscriber::class)
        ->tag('kernel.event_listener', ['event' => 'media.loaded', 'method' => 'unserialize', 'priority' => 100])
        ->tag('kernel.event_listener', ['event' => 'media.partial_loaded', 'method' => 'unserialize', 'priority' => 100]);
    // endregion Event handling

    // region Routes
    $services->set(MediaRoute::class)
        ->public()
        ->args([
            service('media.repository'),
            service(CacheTagCollector::class),
        ]);
    // endregion Routes

    // region Tasks
    $services->set(CleanupCorruptedMediaTask::class)
        ->tag('contena.scheduled.task');
    // endregion Tasks
};

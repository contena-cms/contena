<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Theme\AbstractThemePathBuilder;
use Contena\Frontend\Theme\Aggregate\ThemeChildDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeMediaDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationDefinition;
use Contena\Frontend\Theme\BundleConfig\FrontendBundleConfigStyleFileResolver;
use Contena\Frontend\Theme\CachedResolvedConfigLoader;
use Contena\Frontend\Theme\Command\ThemeChangeCommand;
use Contena\Frontend\Theme\Command\ThemeCompileCommand;
use Contena\Frontend\Theme\Command\ThemeCreateCommand;
use Contena\Frontend\Theme\Command\ThemeDumpCommand;
use Contena\Frontend\Theme\Command\ThemeMigrateTranslationsCommand;
use Contena\Frontend\Theme\Command\ThemePrepareIconsCommand;
use Contena\Frontend\Theme\Command\ThemeRefreshCommand;
use Contena\Frontend\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigDumper;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigLoader;
use Contena\Frontend\Theme\Controller\ThemeController;
use Contena\Frontend\Theme\DataAbstractionLayer\ThemeExceptionHandler;
use Contena\Frontend\Theme\DataAbstractionLayer\ThemeIndexer;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\Extension\LanguageExtension;
use Contena\Frontend\Theme\Extension\MediaExtension;
use Contena\Frontend\Theme\Extension\ChannelExtension;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\Message\CompileThemeFailedSubscriber;
use Contena\Frontend\Theme\Message\CompileThemeHandler;
use Contena\Frontend\Theme\Message\DeleteThemeFilesHandler;
use Contena\Frontend\Theme\ResolvedConfigLoader;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTask;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\SeedingThemePathBuilder;
use Contena\Frontend\Theme\Snippet\ThemeConfigSnippetGenerator;
use Contena\Frontend\Theme\Snippet\ThemeSnippetFileWriter;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Subscriber\FirstRunWizardSubscriber;
use Contena\Frontend\Theme\Subscriber\PluginLifecycleSubscriber;
use Contena\Frontend\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Contena\Frontend\Theme\Subscriber\ThemeSnippetsSubscriber;
use Contena\Frontend\Theme\Subscriber\UnusedMediaSubscriber;
use Contena\Frontend\Theme\Subscriber\UpdateSubscriber;
use Contena\Frontend\Theme\ThemeAppLifecycleHandler;
use Contena\Frontend\Theme\ThemeAssetPackage;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeConfigCacheInvalidator;
use Contena\Frontend\Theme\ThemeDefinition;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;
use Contena\Frontend\Theme\ThemeMergedConfigBuilder;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\ThemeRuntimeConfigStorage;
use Contena\Frontend\Theme\ThemeScripts;
use Contena\Frontend\Theme\ThemeService;
use Contena\Frontend\Theme\Twig\ThemeInheritanceBuilder;
use Contena\Frontend\Theme\Twig\ThemeInheritanceBuilderInterface;
use Contena\Frontend\Theme\Twig\ThemeNamespaceHierarchyBuilder;
use Contena\Frontend\Theme\UnusedThemeDirectoryDeleter;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Theme
    $services->set(FrontendPluginConfigurationFactory::class)
        ->args([
            service(KernelPluginLoader::class),
            service(SourceResolver::class),
            service(Filesystem::class),
        ]);

    $services->set(FrontendPluginRegistry::class)
        ->public()
        ->args([
            service('kernel'),
            service(FrontendPluginConfigurationFactory::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(FrontendBundleConfigStyleFileResolver::class)
        ->args([
            service(FrontendPluginRegistry::class),
        ])
        ->tag('contena.bundle_config.style_file_resolver');

    $services->set(ScssPhpCompiler::class);

    $services->set(ThemeCompiler::class)
        ->args([
            service('contena.filesystem.theme'),
            service('contena.filesystem.temp'),
            service('contena.filesystem.asset'),
            service(CopyBatchInputFactory::class),
            service(ThemeFileResolver::class),
            param('kernel.debug'),
            service(EventDispatcherInterface::class),
            service(ThemeFilesystemResolver::class),
            tagged_iterator('contena.asset'),
            service(CacheInvalidator::class),
            service(LoggerInterface::class),
            service(AbstractThemePathBuilder::class),
            service(ScssPhpCompiler::class),
            param('frontend.theme.allowed_scss_values'),
            param('frontend.theme.validate_on_compile'),
            param('contena.filesystem.theme.visibility'),
        ]);

    $services->set(ThemeLifecycleService::class)
        ->args([
            service(FrontendPluginRegistry::class),
            service('theme.repository'),
            service('media.repository'),
            service('media_folder.repository'),
            service('theme_media.repository'),
            service(FileSaver::class),
            service(FileNameProvider::class),
            service(ThemeFilesystemResolver::class),
            service('language.repository'),
            service('theme_child.repository'),
            service(Connection::class),
            service(FrontendPluginConfigurationFactory::class)->nullOnInvalid(),
            service(ThemeRuntimeConfigService::class),
            service(ThemeSnippetFileWriter::class),
        ]);

    $services->set(ThemeFileResolver::class)
        ->args([
            service(ThemeFilesystemResolver::class),
        ]);

    $services->set(ThemeScripts::class)
        ->args([
            service('request_stack'),
            service(ThemeRuntimeConfigService::class),
            service('contena.filesystem.temp'),
            service(LoggerInterface::class),
        ]);

    $services->set(ThemeMergedConfigBuilder::class)
        ->args([
            service(FrontendPluginRegistry::class),
            service('theme.repository'),
        ]);

    $services->set(ThemeConfigSnippetGenerator::class)
        ->args([
            service(FrontendPluginRegistry::class),
        ]);

    $services->set(ThemeSnippetFileWriter::class)
        ->args([
            service(ThemeConfigSnippetGenerator::class),
            service('contena.filesystem.private'),
            service(CacheInvalidator::class),
            service('logger'),
        ]);

    $services->set(ThemeService::class)
        ->args([
            service(FrontendPluginRegistry::class),
            service('theme.repository'),
            service('theme_channel.repository'),
            service(ThemeCompiler::class),
            service(ScssPhpCompiler::class),
            service('event_dispatcher'),
            service(AbstractConfigLoader::class),
            service(Connection::class),
            service(SystemConfigService::class),
            service('messenger.default_bus'),
            service(NotificationService::class),
            service(ThemeMergedConfigBuilder::class),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ResolvedConfigLoader::class)
        ->lazy()
        ->args([
            service('media.repository'),
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(CachedResolvedConfigLoader::class)
        ->args([
            service(ResolvedConfigLoader::class),
        ])
        ->deprecate('contena/core', '6.8.0', 'tag:v6.8.0 - The %service_id% service will be removed in v6.8.0.0 without replacement')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ThemeConfigCacheInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ThemeLifecycleHandler::class)
        ->args([
            service(ThemeLifecycleService::class),
            service(ThemeService::class),
            service('theme.repository'),
            service(FrontendPluginRegistry::class),
            service(Connection::class),
        ]);

    $services->set(ThemeAppLifecycleHandler::class)
        ->args([
            service(FrontendPluginRegistry::class),
            service(FrontendPluginConfigurationFactory::class),
            service(ThemeLifecycleHandler::class),
            service(ThemeLifecycleService::class),
        ])
        ->tag('contena.app_lifecycle.handler');

    $services->set(DatabaseAvailableThemeProvider::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(DatabaseConfigLoader::class)
        ->args([
            service('theme.repository'),
            service(FrontendPluginRegistry::class),
            service('media.repository'),
        ]);

    $services->set(ThemeRuntimeConfigStorage::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ThemeRuntimeConfigService::class)
        ->args([
            service(ThemeFileResolver::class),
            service(FrontendPluginRegistry::class),
            service(ThemeMergedConfigBuilder::class),
            service(ThemeRuntimeConfigStorage::class),
            service(ClockInterface::class),
        ]);

    $services->set(SeedingThemePathBuilder::class)
        ->lazy()
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(MD5ThemePathBuilder::class);

    $services->set(DeleteThemeFilesHandler::class)
        ->args([
            service('contena.filesystem.theme'),
            service(AbstractThemePathBuilder::class),
        ])
        ->tag('messenger.message_handler')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(CompileThemeHandler::class)
        ->args([
            service(ThemeCompiler::class),
            service(AbstractConfigLoader::class),
            service(FrontendPluginRegistry::class),
            service(NotificationService::class),
            service('channel.repository'),
            service(ThemeRuntimeConfigService::class),
            service('theme_channel.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CompileThemeFailedSubscriber::class)
        ->args([
            service(NotificationService::class),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedThemeDirectoryDeleter::class)
        ->args([
            service(Connection::class),
            service('contena.filesystem.theme'),
            service(AbstractThemePathBuilder::class),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteThemeFilesTask::class)
        ->tag('contena.scheduled.task');

    $services->set(DeleteThemeFilesTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(UnusedThemeDirectoryDeleter::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(StaticFileConfigLoader::class)
        ->args([
            service('contena.filesystem.private'),
        ]);

    $services->set(StaticFileAvailableThemeProvider::class)
        ->args([
            service('contena.filesystem.private'),
        ]);

    $services->set(StaticFileConfigDumper::class)
        ->args([
            service(DatabaseConfigLoader::class),
            service(DatabaseAvailableThemeProvider::class),
            service('contena.filesystem.private'),
            service('contena.filesystem.temp'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set('contena.asset.theme', ThemeAssetPackage::class)
        ->lazy()
        ->args([
            [
                param('contena.filesystem.theme.url'),
            ],
            service('contena.asset.theme.version_strategy'),
            service('request_stack'),
            service(AbstractThemePathBuilder::class),
        ])
        ->tag('contena.asset', ['asset' => 'theme']);

    // Entity definitions
    $services->set(ThemeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ThemeTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ThemeChannelDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ThemeMediaDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ThemeChildDefinition::class)
        ->tag('contena.entity.definition');

    // Entity extensions
    $services->set(ChannelExtension::class)
        ->tag('contena.entity.extension');

    $services->set(LanguageExtension::class)
        ->tag('contena.entity.extension');

    $services->set(MediaExtension::class)
        ->tag('contena.entity.extension');

    // Controller
    $services->set(ThemeController::class)
        ->public()
        ->args([
            service(ThemeService::class),
            service(ScssPhpCompiler::class),
            param('frontend.theme.allowed_scss_values'),
        ])
        ->call('setContainer', [service('service_container')]);

    // Commands
    $services->set(ThemeCreateCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(ThemeChangeCommand::class)
        ->args([
            service(ThemeService::class),
            service(FrontendPluginRegistry::class),
            service('channel.repository'),
            service('theme.repository'),
        ])
        ->tag('console.command');

    $services->set(ThemeCompileCommand::class)
        ->args([
            service(ThemeService::class),
            service(AbstractAvailableThemeProvider::class),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(ThemeDumpCommand::class)
        ->args([
            service(FrontendPluginRegistry::class),
            service(ThemeFileResolver::class),
            service('theme.repository'),
            service(StaticFileConfigDumper::class),
            service(ThemeFilesystemResolver::class),
        ])
        ->tag('console.command');

    $services->set(ThemeMigrateTranslationsCommand::class)
        ->args([
            service(FrontendPluginRegistry::class),
            service(ThemeFilesystemResolver::class),
            service(ThemeConfigSnippetGenerator::class),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(ThemeRefreshCommand::class)
        ->args([
            service(ThemeLifecycleService::class),
        ])
        ->tag('console.command');

    $services->set(ThemePrepareIconsCommand::class)
        ->tag('console.command');

    // Subscriber
    $services->set(PluginLifecycleSubscriber::class)
        ->args([
            service(FrontendPluginRegistry::class),
            param('kernel.project_dir'),
            service(FrontendPluginConfigurationFactory::class),
            service(ThemeLifecycleHandler::class),
            service(ThemeLifecycleService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ThemeInheritanceBuilderInterface::class, ThemeInheritanceBuilder::class)
        ->args([
            service(ThemeRuntimeConfigService::class),
        ]);

    $services->set(ThemeCompilerEnrichScssVarSubscriber::class)
        ->args([
            service(ConfigurationService::class),
            service(FrontendPluginRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    // Twig
    $services->set(ThemeNamespaceHierarchyBuilder::class)
        ->args([
            service(ThemeInheritanceBuilderInterface::class),
            service(DatabaseChannelThemeLoader::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset'])
        // Make sure it runs after default `BundleHierarchyBuilder`
        ->tag('contena.twig.hierarchy_builder', ['priority' => 500]);

    $services->set(FirstRunWizardSubscriber::class)
        ->args([
            service(ThemeService::class),
            service(ThemeLifecycleService::class),
            service('theme.repository'),
            service('theme_channel.repository'),
            service('channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UpdateSubscriber::class)
        ->args([
            service(ThemeService::class),
            service(ThemeLifecycleService::class),
            service('channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedMediaSubscriber::class)
        ->args([
            service('theme.repository'),
            service(ThemeService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ThemeIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('theme.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ])
        ->tag('contena.entity_indexer');

    $services->set(DatabaseChannelThemeLoader::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    $services->set(ThemeExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(ThemeFilesystemResolver::class)
        ->public()
        ->args([
            service(SourceResolver::class),
            service('kernel'),
        ]);

    $services->set(ThemeSnippetsSubscriber::class)
        ->args([
            service(ThemeRuntimeConfigService::class),
            service(DatabaseChannelThemeLoader::class),
        ])
        ->tag('kernel.event_subscriber');
};

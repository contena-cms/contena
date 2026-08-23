<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Media\File\FileNameProvider;
use Contena\Core\Content\Media\File\FileSaver;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Plugin\BundleConfigStyleFileResolver;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Framework\Twig\Extension\ConfigExtension;
use Contena\Frontend\Framework\Twig\TemplateConfigAccessor;
use Contena\Frontend\Theme\AbstractResolvedConfigLoader;
use Contena\Frontend\Theme\AbstractScssCompiler;
use Contena\Frontend\Theme\AbstractThemePathBuilder;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChildDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeMediaDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeTranslationDefinition;
use Contena\Frontend\Theme\BundleConfig\FrontendBundleConfigStyleFileResolver;
use Contena\Frontend\Theme\Command\ThemeChangeCommand;
use Contena\Frontend\Theme\Command\ThemeCompileCommand;
use Contena\Frontend\Theme\Command\ThemeDumpCommand;
use Contena\Frontend\Theme\Command\ThemePrepareIconsCommand;
use Contena\Frontend\Theme\Command\ThemeRefreshCommand;
use Contena\Frontend\Theme\ConfigLoader\AbstractConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\DatabaseAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\ConfigLoader\StaticFileAvailableThemeProvider;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigDumper;
use Contena\Frontend\Theme\ConfigLoader\StaticFileConfigLoader;
use Contena\Frontend\Theme\Controller\ThemeController;
use Contena\Frontend\Theme\DataAbstractionLayer\ThemeIndexer;
use Contena\Frontend\Theme\DatabaseChannelThemeLoader;
use Contena\Frontend\Theme\Extension\ChannelExtension;
use Contena\Frontend\Theme\Extension\LanguageExtension;
use Contena\Frontend\Theme\Extension\MediaExtension;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Mail\MailThemeConfigSubscriber;
use Contena\Frontend\Theme\Mail\MailThemeIdLoader;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\Message\CompileThemeFailedSubscriber;
use Contena\Frontend\Theme\Message\CompileThemeHandler;
use Contena\Frontend\Theme\ResolvedConfigLoader;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTask;
use Contena\Frontend\Theme\ScheduledTask\DeleteThemeFilesTaskHandler;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\Subscriber\PluginLifecycleSubscriber;
use Contena\Frontend\Theme\Subscriber\ThemeCompilerEnrichScssVarSubscriber;
use Contena\Frontend\Theme\Subscriber\ThemeSnippetsSubscriber;
use Contena\Frontend\Theme\Subscriber\UnusedMediaSubscriber;
use Contena\Frontend\Theme\Subscriber\UpdateSubscriber;
use Contena\Frontend\Theme\ThemeAssetPackage;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeCompilerInterface;
use Contena\Frontend\Theme\ThemeConfigCacheInvalidator;
use Contena\Frontend\Theme\ThemeConfigValueAccessor;
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

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services()->defaults()->autowire()->autoconfigure();

    $services->set(FrontendPluginConfigurationFactory::class);
    $services->alias(AbstractFrontendPluginConfigurationFactory::class, FrontendPluginConfigurationFactory::class);
    $services->set(FrontendPluginRegistry::class)
        ->public()
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScssPhpCompiler::class);
    $services->alias(AbstractScssCompiler::class, ScssPhpCompiler::class);
    $services->set(MD5ThemePathBuilder::class);
    $services->alias(AbstractThemePathBuilder::class, MD5ThemePathBuilder::class);
    $services->set(BundleConfigStyleFileResolver::class, FrontendBundleConfigStyleFileResolver::class);
    $services->set(ThemeFilesystemResolver::class)
        ->public()
        ->args([service('kernel')]);
    $services->set(ThemeFileResolver::class);

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
    $services->alias(ThemeCompilerInterface::class, ThemeCompiler::class);

    $services->set(ThemeLifecycleService::class)
        ->args([
            service(FrontendPluginRegistry::class), service('theme.repository'), service('media.repository'),
            service('media_folder.repository'), service('theme_media.repository'), service(FileSaver::class),
            service(FileNameProvider::class), service(ThemeFilesystemResolver::class),
            service('theme_child.repository'), service(Connection::class),
            service(AbstractFrontendPluginConfigurationFactory::class), service(ThemeRuntimeConfigService::class),
        ]);
    $services->set(ThemeRuntimeConfigStorage::class);
    $services->set(ThemeRuntimeConfigService::class);
    $services->set(ThemeMergedConfigBuilder::class)->arg('$themeRepository', service('theme.repository'));
    $services->set(ThemeService::class)
        ->args([
            service(FrontendPluginRegistry::class), service('theme.repository'), service('theme_channel.repository'),
            service(ThemeCompilerInterface::class), service(AbstractScssCompiler::class), service(EventDispatcherInterface::class),
            service(AbstractConfigLoader::class), service(Connection::class), service(SystemConfigService::class),
            service('messenger.default_bus'), service(NotificationService::class), service(ThemeMergedConfigBuilder::class),
            service(ThemeRuntimeConfigService::class),
        ]);
    $services->set(ThemeLifecycleHandler::class)
        ->args([
            service(ThemeLifecycleService::class), service(ThemeService::class), service('theme.repository'),
            service(FrontendPluginRegistry::class), service(Connection::class),
        ]);

    $services->set(ResolvedConfigLoader::class)
        ->lazy()
        ->args([
            service('media.repository'),
            service(ThemeRuntimeConfigService::class),
        ]);
    $services->alias(AbstractResolvedConfigLoader::class, ResolvedConfigLoader::class);
    $services->set(DatabaseChannelThemeLoader::class)
        ->public()->tag('kernel.reset', ['method' => 'reset']);
    $services->set(DatabaseAvailableThemeProvider::class);
    $services->set(StaticFileConfigLoader::class)->args([service('contena.filesystem.private')]);
    $services->set(StaticFileAvailableThemeProvider::class)->args([service('contena.filesystem.private')]);
    $services->set(DatabaseConfigLoader::class)
        ->args([service('theme.repository'), service(FrontendPluginRegistry::class), service('media.repository')]);
    $services->set(StaticFileConfigDumper::class)
        ->args([
            service(DatabaseConfigLoader::class), service(DatabaseAvailableThemeProvider::class),
            service('contena.filesystem.private'), service('contena.filesystem.temp'),
        ])->tag('kernel.event_subscriber');

    $services->set(ThemeConfigCacheInvalidator::class)->tag('kernel.event_subscriber');
    $services->set(ThemeConfigValueAccessor::class)
        ->args([service(AbstractResolvedConfigLoader::class), service(CacheTagCollector::class), service(ThemeRuntimeConfigService::class)]);
    $services->set(ThemeScripts::class)
        ->args([service('request_stack'), service(ThemeRuntimeConfigService::class), service('contena.filesystem.temp'), service(LoggerInterface::class)]);
    $services->set(TemplateConfigAccessor::class)
        ->args([service(ThemeConfigValueAccessor::class), service(ThemeScripts::class), param('kernel.environment'), tagged_iterator('contena.asset', 'asset')]);
    $services->set(ConfigExtension::class)->tag('twig.extension');

    $services->set(ThemeAssetPackage::class)
        ->args([[param('contena.filesystem.theme.url')], service('contena.asset.theme.version_strategy'), service('request_stack'), service(AbstractThemePathBuilder::class)])
        ->tag('contena.asset', ['asset' => 'theme']);

    $services->set(ThemeDefinition::class)->tag('contena.entity.definition');
    $services->set(ThemeChannelDefinition::class)->tag('contena.entity.definition');
    $services->set(ThemeTranslationDefinition::class)->tag('contena.entity.definition');
    $services->set(ThemeMediaDefinition::class)->tag('contena.entity.definition');
    $services->set(ThemeChildDefinition::class)->tag('contena.entity.definition');
    $services->set(ChannelExtension::class)->tag('contena.entity.extension');
    $services->set(LanguageExtension::class)->tag('contena.entity.extension');
    $services->set(MediaExtension::class)->tag('contena.entity.extension');
    $services->set(ThemeIndexer::class)->args([
        service(IteratorFactory::class), service('theme.repository'), service(Connection::class), service('event_dispatcher'),
    ])->tag('contena.entity_indexer');

    $services->set(ThemeController::class)->public()->arg('$customAllowedRegex', param('frontend.theme.allowed_scss_values'));
    $services->set(ThemeInheritanceBuilderInterface::class, ThemeInheritanceBuilder::class);
    $services->set(ThemeNamespaceHierarchyBuilder::class)
        ->args([service(ThemeInheritanceBuilderInterface::class), service(DatabaseChannelThemeLoader::class)])
        ->tag('kernel.event_subscriber')->tag('kernel.reset', ['method' => 'reset'])
        ->tag('contena.twig.hierarchy_builder', ['priority' => 500]);

    $services->set(ThemeCompilerEnrichScssVarSubscriber::class)->args([
        service(ConfigurationService::class), service(FrontendPluginRegistry::class),
    ])->tag('kernel.event_subscriber');
    $services->set(ThemeSnippetsSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(MailThemeIdLoader::class);
    $services->set(MailThemeConfigSubscriber::class)->tag('kernel.event_subscriber');
    $services->set(UnusedMediaSubscriber::class)->args([service('theme.repository'), service(ThemeService::class)])->tag('kernel.event_subscriber');
    $services->set(UpdateSubscriber::class)->args([service(ThemeService::class), service(ThemeLifecycleService::class), service('channel.repository')])->tag('kernel.event_subscriber');
    $services->set(PluginLifecycleSubscriber::class)->args([
        service(FrontendPluginRegistry::class), param('kernel.project_dir'), service(AbstractFrontendPluginConfigurationFactory::class),
        service(ThemeLifecycleHandler::class), service(ThemeLifecycleService::class),
    ])->tag('kernel.event_subscriber');

    $services->set(CompileThemeHandler::class)->args([
        service(ThemeCompilerInterface::class), service(AbstractConfigLoader::class), service(FrontendPluginRegistry::class),
        service(NotificationService::class), service('channel.repository'), service(ThemeRuntimeConfigService::class),
        service('theme_channel.repository'), service('event_dispatcher'), service(SystemConfigService::class),
    ])->tag('messenger.message_handler');
    $services->set(CompileThemeFailedSubscriber::class)->args([
        service(NotificationService::class), service(SystemConfigService::class),
    ])->tag('kernel.event_subscriber');
    $services->set(UnusedThemeDirectoryDeleter::class)->args([
        service(Connection::class), service('contena.filesystem.theme'), service(AbstractThemePathBuilder::class), service(ClockInterface::class),
    ]);
    $services->set(DeleteThemeFilesTask::class)->tag('contena.scheduled.task');
    $services->set(DeleteThemeFilesTaskHandler::class)->args([
        service('scheduled_task.repository'), service('logger'), service(UnusedThemeDirectoryDeleter::class),
    ])->tag('messenger.message_handler');

    $services->set(ThemeRefreshCommand::class)
        ->args([service(ThemeLifecycleService::class), service(Connection::class)])
        ->tag('console.command');

    foreach ([ThemeChangeCommand::class, ThemeCompileCommand::class, ThemeDumpCommand::class, ThemePrepareIconsCommand::class] as $command) {
        $services->set($command)->tag('console.command');
    }
};

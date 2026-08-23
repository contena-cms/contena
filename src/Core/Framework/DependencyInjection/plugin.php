<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Composer\Autoload\ClassLoader;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Contena\Core\Framework\Plugin\BundleConfigGenerator;
use Contena\Core\Framework\Plugin\BundleConfigStyleFileResolver;
use Contena\Core\Framework\Plugin\Command\BundleDumpCommand;
use Contena\Core\Framework\Plugin\Command\Lifecycle\PluginActivateCommand;
use Contena\Core\Framework\Plugin\Command\Lifecycle\PluginDeactivateCommand;
use Contena\Core\Framework\Plugin\Command\Lifecycle\PluginInstallCommand;
use Contena\Core\Framework\Plugin\Command\Lifecycle\PluginUninstallCommand;
use Contena\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateAllCommand;
use Contena\Core\Framework\Plugin\Command\Lifecycle\PluginUpdateCommand;
use Contena\Core\Framework\Plugin\Command\MakerCommand;
use Contena\Core\Framework\Plugin\Command\PluginCreateCommand;
use Contena\Core\Framework\Plugin\Command\PluginListCommand;
use Contena\Core\Framework\Plugin\Command\PluginRefreshCommand;
use Contena\Core\Framework\Plugin\Command\PluginZipImportCommand;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\AdminModuleGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\ChannelApiRouteGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\CommandGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\ComposerGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\ConfigGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\CustomFieldsetGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\EntityGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\EventSubscriberGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\FrontendControllerGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\GitignoreGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\JavascriptPluginGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\PluginClassGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\ScheduledTaskGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\Generator\TestsGenerator;
use Contena\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Contena\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Contena\Core\Framework\Plugin\Composer\CommandExecutor;
use Contena\Core\Framework\Plugin\Composer\PackageProvider;
use Contena\Core\Framework\Plugin\ExtensionExtractor;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Plugin\KernelPluginLoader\ComposerPluginLoader;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Plugin\NullBundleConfigStyleFileResolver;
use Contena\Core\Framework\Plugin\PluginDefinition;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Plugin\PluginZipDetector;
use Contena\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Contena\Core\Framework\Plugin\Subscriber\PluginAclPrivilegesSubscriber;
use Contena\Core\Framework\Plugin\Subscriber\PluginLoadedSubscriber;
use Contena\Core\Framework\Plugin\Telemetry\PluginTelemetrySubscriber;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Framework\Plugin\Util\PluginFinder;
use Contena\Core\Framework\Plugin\Util\PluginIdProvider;
use Contena\Core\Framework\Plugin\Util\VersionSanitizer;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set('maker.auto_command.abstract', MakerCommand::class)
        ->abstract()
        ->args([
            '', // maker
            service(ScaffoldingCollector::class),
            service(ScaffoldingWriter::class),
            service(PluginService::class),
        ]);

    $services->set(KernelPluginLoader::class)
        ->public()
        ->factory([service('kernel'), 'getPluginLoader']);

    $services->set(ClassLoader::class)
        ->factory([service(KernelPluginLoader::class), 'getClassLoader']);

    $services->set(KernelPluginCollection::class)
        ->public()
        ->factory([service(KernelPluginLoader::class), 'getPluginInstances']);

    $services->set(BundleDumpCommand::class)
        ->args([
            service(BundleConfigGenerator::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(BundleConfigStyleFileResolver::class, NullBundleConfigStyleFileResolver::class);

    $services->set(BundleConfigGenerator::class)
        ->args([
            service('kernel'),
        ]);

    $services->set(PluginDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(PluginTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(PluginService::class)
        ->args([
            param('kernel.plugin_dir'),
            param('kernel.project_dir'),
            service('plugin.repository'),
            service('language.repository'),
            service(PluginFinder::class),
            service(VersionSanitizer::class),
        ]);

    $services->set(PluginLifecycleService::class)
        ->args([
            service('plugin.repository'),
            service('event_dispatcher'),
            service(KernelPluginCollection::class),
            service('service_container'),
            service(MigrationCollectionLoader::class),
            service(AssetService::class),
            service(CommandExecutor::class),
            service(RequirementsValidator::class),
            service('cache.messenger.restart_workers_signal'),
            param('kernel.contena_version'),
            service(SystemConfigService::class),
            service(PluginService::class),
            service(VersionSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service(RequestStack::class),
            service(CustomFieldSetPersister::class),
            service(ClockInterface::class),
        ]);

    $services->set(PluginManagementService::class)
        ->args([
            param('kernel.project_dir'),
            service(PluginZipDetector::class),
            service(ExtensionExtractor::class),
            service(PluginService::class),
            service(Filesystem::class),
            service(CacheClearer::class),
        ]);

    $services->set(ExtensionExtractor::class)
        ->args([
            [
                'plugin' => param('kernel.plugin_dir'),
                'app' => param('kernel.app_dir'),
            ],
            service(Filesystem::class),
        ]);

    $services->set(PluginZipDetector::class);

    $services->set(ComposerPluginLoader::class)
        ->args([
            service(ClassLoader::class),
        ]);

    // Commands
    $services->set(PluginRefreshCommand::class)
        ->args([
            service(PluginService::class),
        ])
        ->tag('console.command');

    $services->set(PluginListCommand::class)
        ->args([
            service('plugin.repository'),
            service(ComposerPluginLoader::class),
        ])
        ->tag('console.command');

    $services->set(PluginZipImportCommand::class)
        ->args([
            service(PluginManagementService::class),
            service(PluginService::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginInstallCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(PluginActivateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUpdateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginDeactivateCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUninstallCommand::class)
        ->args([
            service(PluginLifecycleService::class),
            service('plugin.repository'),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(PluginUpdateAllCommand::class)
        ->args([
            service(PluginService::class),
            service('plugin.repository'),
            service(PluginLifecycleService::class),
        ])
        ->tag('console.command');

    $services->set(PluginLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(PluginAclPrivilegesSubscriber::class)
        ->args([
            service(KernelPluginCollection::class),
        ])
        ->tag('kernel.event_subscriber');

    // Composer
    $services->set(PackageProvider::class);

    $services->set(CommandExecutor::class)
        ->lazy()
        ->args([
            param('kernel.project_dir'),
        ]);

    // Helper
    $services->set(PluginIdProvider::class)
        ->public()
        ->args([
            service('plugin.repository'),
        ]);

    $services->set(AssetService::class)
        ->args([
            service('contena.filesystem.asset'),
            service('contena.filesystem.private'),
            service('kernel'),
            service(KernelPluginLoader::class),
            service(CacheInvalidator::class),
            service('parameter_bag'),
            service('event_dispatcher'),
        ]);

    // Requirement
    $services->set(RequirementsValidator::class)
        ->args([
            service('plugin.repository'),
            param('kernel.project_dir'),
        ]);

    $services->set(PluginFinder::class)
        ->args([
            service(PackageProvider::class),
        ]);

    $services->set(VersionSanitizer::class);

    $services->set(PluginCreateCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(ScaffoldingCollector::class),
            service(ScaffoldingWriter::class),
            service(Filesystem::class),
            tagged_iterator('contena.scaffold.generator'),
        ])
        ->tag('console.command');

    $services->set(ScaffoldingCollector::class)
        ->args([
            tagged_iterator('contena.scaffold.generator'),
        ]);

    $services->set(ScaffoldingWriter::class)
        ->args([
            service(Filesystem::class),
        ]);

    $services->set(ComposerGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(PluginClassGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(TestsGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(CommandGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(ScheduledTaskGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(EventSubscriberGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(FrontendControllerGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(ChannelApiRouteGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(EntityGenerator::class)
        ->args([
            service(ClockInterface::class),
        ])
        ->tag('contena.scaffold.generator');

    $services->set(ConfigGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(JavascriptPluginGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(AdminModuleGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(CustomFieldsetGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(GitignoreGenerator::class)
        ->tag('contena.scaffold.generator');

    $services->set(PluginTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.telemetry.subscriber');
};

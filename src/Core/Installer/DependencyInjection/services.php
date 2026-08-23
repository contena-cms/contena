<?php declare(strict_types=1);

namespace Contena\Core\Installer\DependencyInjection;

use Composer\Composer;
use Composer\Repository\PlatformRepository;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Contena\Core\Framework\Plugin\Composer\Factory;
use Contena\Core\Installer\Configuration\AdminConfigurationService;
use Contena\Core\Installer\Configuration\EnvConfigWriter;
use Contena\Core\Installer\Controller\DatabaseConfigurationController;
use Contena\Core\Installer\Controller\DatabaseImportController;
use Contena\Core\Installer\Controller\FinishController;
use Contena\Core\Installer\Controller\LicenseController;
use Contena\Core\Installer\Controller\RequirementsController;
use Contena\Core\Installer\Controller\StartController;
use Contena\Core\Installer\Controller\SystemConfigurationController;
use Contena\Core\Installer\Controller\TranslationController;
use Contena\Core\Installer\Database\BlueGreenDeploymentService;
use Contena\Core\Installer\Database\DatabaseMigrator;
use Contena\Core\Installer\Database\MigrationCollectionFactory;
use Contena\Core\Installer\Finish\SystemLocker;
use Contena\Core\Installer\Finish\UniqueIdGenerator;
use Contena\Core\Installer\License\LicenseFetcher;
use Contena\Core\Installer\Requirements\ConfigurationRequirementsValidator;
use Contena\Core\Installer\Requirements\EnvironmentRequirementsValidator;
use Contena\Core\Installer\Requirements\FilesystemRequirementsValidator;
use Contena\Core\Installer\Requirements\IniConfigReader;
use Contena\Core\Installer\Subscriber\InstallerLocaleListener;
use Contena\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Contena\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\Snippet\Service\AbstractTranslationConfigLoader;
use Contena\Core\System\Snippet\Service\TranslationConfigLoader;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('contena.installer.supportedLanguages', [
        'zh-CN' => ['id' => 'zh-CN', 'label' => '简体中文'],
        'en' => ['id' => 'en-GB', 'label' => 'English (UK)'],
    ]);

    $parameters->set('contena.installer.tosUrls', [
        'zh-CN' => 'https://api.contena.cn/gtc/zh_CN.html',
        'en' => 'https://api.contena.cn/gtc/en_GB.html',
    ]);

    $parameters->set('env(CONTENA_ADMINISTRATION_PATH_NAME)', 'admin');

    $services = $containerConfigurator->services();

    $services->set('contena.asset.asset', FallbackUrlPackage::class)
        ->args([
            [
                '',
            ],
            service('contena.asset.version_strategy'),
        ])
        ->tag('assets.package', ['package' => 'asset']);

    $services->set('contena.asset.version_strategy', EmptyVersionStrategy::class);

    $services->set(InstallerLocaleListener::class)
        ->args([
            param('contena.installer.supportedLanguages'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PlatformRepository::class);

    $services->set(Composer::class)
        ->factory([Factory::class, 'createComposer'])
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(EnvironmentRequirementsValidator::class)
        ->args([
            service(Composer::class),
            service(PlatformRepository::class),
        ])
        ->tag('contena.installer.requirement');

    $services->set(FilesystemRequirementsValidator::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('contena.installer.requirement');

    $services->set(ConfigurationRequirementsValidator::class)
        ->args([
            service(IniConfigReader::class),
        ])
        ->tag('contena.installer.requirement');

    $services->set(IniConfigReader::class);

    $services->set('contena.installer.guzzle', Client::class);

    $services->alias(AbstractTranslationConfigLoader::class, TranslationConfigLoader::class);

    $services->set(TranslationConfigLoader::class)
        ->args([
            service('filesystem'),
        ]);

    $services->set(TranslationConfig::class)
        ->lazy()
        ->public()
        ->factory([service(AbstractTranslationConfigLoader::class), 'load']);

    $services->set(LicenseFetcher::class)
        ->args([
            service('contena.installer.guzzle'),
            param('contena.installer.tosUrls'),
        ]);

    $services->set(StartController::class)
        ->public()
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(RequirementsController::class)
        ->public()
        ->args([
            tagged_iterator('contena.installer.requirement'),
            param('kernel.project_dir'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(LicenseController::class)
        ->public()
        ->args([
            service(LicenseFetcher::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(DatabaseConfigurationController::class)
        ->public()
        ->args([
            service('translator'),
            service(BlueGreenDeploymentService::class),
            service(SetupDatabaseAdapter::class),
            service(DatabaseConnectionFactory::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(DatabaseImportController::class)
        ->public()
        ->args([
            service(DatabaseConnectionFactory::class),
            service(DatabaseMigrator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(FinishController::class)
        ->public()
        ->args([
            service(SystemLocker::class),
            service(Client::class),
            env('APP_URL')->string(),
            service(ClockInterface::class),
            env('CONTENA_ADMINISTRATION_PATH_NAME')->string(),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SystemConfigurationController::class)
        ->public()
        ->args([
            service(DatabaseConnectionFactory::class),
            service(EnvConfigWriter::class),
            service(AdminConfigurationService::class),
            service('translator'),
            param('contena.installer.supportedLanguages'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(BlueGreenDeploymentService::class);

    $services->set(SetupDatabaseAdapter::class);

    $services->set(DatabaseConnectionFactory::class);

    $services->set(DatabaseMigrator::class)
        ->args([
            service(SetupDatabaseAdapter::class),
            service(MigrationCollectionFactory::class),
            param('kernel.contena_version'),
            service(IniConfigReader::class),
            service(ClockInterface::class),
        ]);

    $services->set(MigrationCollectionFactory::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(EnvConfigWriter::class)
        ->args([
            param('kernel.project_dir'),
            service(UniqueIdGenerator::class),
        ]);

    $services->set(AdminConfigurationService::class)
        ->args([
            service(ClockInterface::class),
            service(AbstractNumberRangeValueGenerator::class),
        ]);

    $services->set(SystemLocker::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(UniqueIdGenerator::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(TranslationController::class)
        ->public()
        ->args([
            param('kernel.project_dir'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(Client::class);
};

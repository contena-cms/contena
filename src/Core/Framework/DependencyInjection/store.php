<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Contena\Core\Framework\Store\Api\ExtensionStoreDataController;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Contena\Core\Framework\Store\Services\ExtensionDataProvider;
use Contena\Core\Framework\Store\Services\ExtensionLifecycleService;
use Contena\Core\Framework\Store\Services\ExtensionLoader;
use Contena\Core\Framework\Store\Session\FrontendSessionStorageFactory;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(INSTANCE_ID)', '');
    $parameters->set('instance_id', env('INSTANCE_ID'));
    $parameters->set('in_app_purchases.active_purchases', '/swplatform/inappfeatures/purchases');
    $parameters->set('contena.store_endpoints', [
        'my_extensions' => '/swplatform/licenseenvironment',
        'my_plugin_updates' => '/swplatform/pluginupdates',
        'environment_information' => '/swplatform/environmentinformation',
        'updater_extension_compatibility' => '/swplatform/autoupdate',
        'updater_permission' => '/swplatform/autoupdate/permission',
        'plugin_download' => '/swplatform/pluginfiles/{pluginName}',
        'app_generate_signature' => '/swplatform/generatesignature',
        'cancel_license' => '/swplatform/pluginlicenses/%s/cancel',
        'login' => '/swplatform/login',
        'create_rating' => '/swplatform/extensionstore/extensions/%s/ratings',
        'user_info' => '/swplatform/userinfo',
    ]);

    $services = $containerConfigurator->services();

    $services->set(FrontendSessionStorageFactory::class)
        ->decorate('session.storage.factory.native')
        ->args([
            service(FrontendSessionStorageFactory::class . '.inner'),
            param('contena.store.use_channel_cookie_path'),
        ]);

    $services->set(AbstractExtensionDataProvider::class, ExtensionDataProvider::class)
        ->args([
            service(ExtensionLoader::class),
            service('plugin.repository'),
        ]);

    $services->set(ExtensionLoader::class)
        ->args([
            service(ConfigurationService::class),
            service('logger'),
            service('event_dispatcher'),
        ]);

    $services->set(ExtensionStoreDataController::class)
        ->public()
        ->args([
            service(AbstractExtensionDataProvider::class),
            service('user.repository'),
            service('language.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ExtensionLifecycleService::class)
        ->args([
            service(PluginService::class),
            service(PluginLifecycleService::class),
            service(PluginManagementService::class),
        ]);

    $services->set(ExtensionStoreActionsController::class)
        ->public()
        ->args([
            service(ExtensionLifecycleService::class),
            service(PluginService::class),
            service(PluginManagementService::class),
            service(Filesystem::class),
            param('contena.deployment.runtime_extension_management'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};

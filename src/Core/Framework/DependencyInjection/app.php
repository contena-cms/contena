<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\ActionButton\ActionButtonLoader;
use Contena\Core\Framework\App\ActionButton\AppActionLoader;
use Contena\Core\Framework\App\ActionButton\Executor;
use Contena\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Contena\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Contena\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Contena\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Contena\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Contena\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification\AppContentSystemBindingSpecificationDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemElementType\AppContentSystemElementTypeDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset\AppContentSystemLayoutPresetDefinition;
use Contena\Core\Framework\App\Aggregate\AppContentSystemStyleOption\AppContentSystemStyleOptionDefinition;
use Contena\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Contena\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Contena\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Contena\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition;
use Contena\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Contena\Core\Framework\App\Api\AppActionController;
use Contena\Core\Framework\App\Api\AppCmsController;
use Contena\Core\Framework\App\Api\AppPrivilegeController;
use Contena\Core\Framework\App\Api\AppSecretRotationController;
use Contena\Core\Framework\App\Api\InstallationIdController;
use Contena\Core\Framework\App\Api\VerifyInstallationController;
use Contena\Core\Framework\App\AppArchiveValidator;
use Contena\Core\Framework\App\AppDefinition;
use Contena\Core\Framework\App\AppDownloader;
use Contena\Core\Framework\App\AppExtractor;
use Contena\Core\Framework\App\AppLocaleProvider;
use Contena\Core\Framework\App\AppSecretResolver;
use Contena\Core\Framework\App\AppService;
use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\App\Cms\BlockTemplateLoader;
use Contena\Core\Framework\App\Command\ActivateAppCommand;
use Contena\Core\Framework\App\Command\AppListCommand;
use Contena\Core\Framework\App\Command\AppPrinter;
use Contena\Core\Framework\App\Command\AppUrlVerificationStatusCommand;
use Contena\Core\Framework\App\Command\AppUrlVerifyCommand;
use Contena\Core\Framework\App\Command\ChangeInstallationIdCommand;
use Contena\Core\Framework\App\Command\CheckInstallationIdCommand;
use Contena\Core\Framework\App\Command\CreateAppCommand;
use Contena\Core\Framework\App\Command\DeactivateAppCommand;
use Contena\Core\Framework\App\Command\InstallAppCommand;
use Contena\Core\Framework\App\Command\RefreshAppCommand;
use Contena\Core\Framework\App\Command\RotateAppSecretCommand;
use Contena\Core\Framework\App\Command\UninstallAppCommand;
use Contena\Core\Framework\App\Command\ValidateAppCommand;
use Contena\Core\Framework\App\Consent\AppConsentDefinitionProvider;
use Contena\Core\Framework\App\Consent\ConsentFeatureDefinition;
use Contena\Core\Framework\App\Cookie\AppCookieCollectListener;
use Contena\Core\Framework\App\Cookie\CookieFeatureDefinition;
use Contena\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Contena\Core\Framework\App\DeletedApps\RememberDeletedAppsSecretSubscriber;
use Contena\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Contena\Core\Framework\App\Delta\DomainsDeltaProvider;
use Contena\Core\Framework\App\Delta\PermissionsDeltaProvider;
use Contena\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Contena\Core\Framework\App\Feature\AppFeatureLifecycleHandler;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\Flow\Action\AppFlowActionLoadedSubscriber;
use Contena\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Contena\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Contena\Core\Framework\App\Hmac\QuerySigner;
use Contena\Core\Framework\App\InstallationId\Fingerprint\AppUrl;
use Contena\Core\Framework\App\InstallationId\Fingerprint\ChannelDomainUrls;
use Contena\Core\Framework\App\InstallationId\Fingerprint\InstallationPath;
use Contena\Core\Framework\App\InstallationId\FingerprintGenerator;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\InstallationIdChangeResolver\MoveInstallationPermanentlyStrategy;
use Contena\Core\Framework\App\InstallationIdChangeResolver\ReinstallAppsStrategy;
use Contena\Core\Framework\App\InstallationIdChangeResolver\Resolver;
use Contena\Core\Framework\App\InstallationIdChangeResolver\UninstallAppsStrategy;
use Contena\Core\Framework\App\Lifecycle\AppFeatureValidator;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Contena\Core\Framework\App\Lifecycle\AppLoader;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Contena\Core\Framework\App\Lifecycle\Handler\ActionButtonLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\CmsBlockLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\ContentSystemBindingSpecificationLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\ContentSystemElementTypeLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\ContentSystemLayoutPresetLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\ContentSystemStyleOptionLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\CustomFieldLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\FlowActionLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\FlowEventLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\RuleConditionLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\TemplateLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\Handler\WebhookLifecycleHandler;
use Contena\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Contena\Core\Framework\App\Lifecycle\Persister\ContentSystemBindingSpecificationPersister;
use Contena\Core\Framework\App\Lifecycle\Persister\ContentSystemElementTypePersister;
use Contena\Core\Framework\App\Lifecycle\Persister\ContentSystemLayoutPresetPersister;
use Contena\Core\Framework\App\Lifecycle\Persister\ContentSystemStyleOptionPersister;
use Contena\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Contena\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Contena\Core\Framework\App\Lifecycle\ScriptFileReader;
use Contena\Core\Framework\App\Manifest\ManifestFactory;
use Contena\Core\Framework\App\MessageHandler\RotateAppSecretHandler;
use Contena\Core\Framework\App\Module\ModuleFeatureDefinition;
use Contena\Core\Framework\App\Module\ModuleLoader;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\App\Privileges\Privileges;
use Contena\Core\Framework\App\ScheduledTask\DeleteCascadeAppsHandler;
use Contena\Core\Framework\App\ScheduledTask\DeleteCascadeAppsTask;
use Contena\Core\Framework\App\ScheduledTask\SystemHeartbeatHandler;
use Contena\Core\Framework\App\ScheduledTask\SystemHeartbeatTask;
use Contena\Core\Framework\App\Source\Local;
use Contena\Core\Framework\App\Source\NoDatabaseSourceResolver;
use Contena\Core\Framework\App\Source\RemoteZip;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Contena\Core\Framework\App\Subscriber\AppLoadedSubscriber;
use Contena\Core\Framework\App\Subscriber\AppScriptConditionConstraintsSubscriber;
use Contena\Core\Framework\App\Subscriber\CustomFieldProtectionSubscriber;
use Contena\Core\Framework\App\Telemetry\AppTelemetrySubscriber;
use Contena\Core\Framework\App\Template\TemplateDefinition;
use Contena\Core\Framework\App\Template\TemplateLoader;
use Contena\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Contena\Core\Framework\App\Url\AppUrlVerifier;
use Contena\Core\Framework\App\Validation\AppNameValidator;
use Contena\Core\Framework\App\Validation\AppRequirementsValidator;
use Contena\Core\Framework\App\Validation\ConfigValidator;
use Contena\Core\Framework\App\Validation\ContentSystemBindingSpecificationAppValidator;
use Contena\Core\Framework\App\Validation\ContentSystemElementTypeAppValidator;
use Contena\Core\Framework\App\Validation\ContentSystemLayoutPresetAppValidator;
use Contena\Core\Framework\App\Validation\ContentSystemStyleOptionAppValidator;
use Contena\Core\Framework\App\Validation\HookableValidator;
use Contena\Core\Framework\App\Validation\ManifestValidator;
use Contena\Core\Framework\App\Validation\Requirements\PublicAccess;
use Contena\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Contena\Core\Framework\App\Validation\TranslationValidator;
use Contena\Core\Framework\ContentSystem\Binding\Loader\YamlBindingSpecificationLoader;
use Contena\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader\YamlStyleOptionLoader;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\ContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Serialization\StyleOptionSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Validation\StyleOptionCollisionDetector;
use Contena\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Contena\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Contena\Core\Framework\ContentSystem\Layout\Type\Validation\ElementTypeCollisionDetector;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Plugin\Util\AssetService;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Webhook\BusinessEventEncoder;
use Contena\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Contena\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Contena\Core\Framework\Webhook\WebhookCacheClearer;
use Contena\Core\System\CustomEntity\CustomEntityLifecycleService;
use Contena\Core\System\CustomField\CustomFieldSetPersister;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\SystemConfig\Util\ConfigReader;
use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('contena.app_dir', '%kernel.project_dir%/custom/apps');

    $services = $containerConfigurator->services();

    $services->set(ManifestFactory::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(AppLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldProtectionSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppScriptConditionConstraintsSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(InstallationIdProvider::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(FingerprintGenerator::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ModuleLoader::class)
        ->args([
            service('app.repository'),
            service(InstallationIdProvider::class),
            service(QuerySigner::class),
            service(AppFeatureStorage::class),
            service(AppSecretResolver::class),
        ]);

    $services->set(TranslationValidator::class)
        ->tag('contena.app_manifest.validator');

    $services->set(AppNameValidator::class)
        ->args([
            service(SourceResolver::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(ManifestValidator::class)
        ->args([
            tagged_iterator('contena.app_manifest.validator'),
        ]);

    $services->set(ConfigValidator::class)
        ->args([
            service(ConfigReader::class),
            service(SourceResolver::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(HookableValidator::class)
        ->args([
            service(HookableEventCollector::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(SecureUrlValidator::class);

    $services->set(PublicAccess::class)
        ->args([
            service(SecureUrlValidator::class),
            service('contena.app_system.guzzle'),
        ])
        ->tag('app.requirements_validator')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppRequirementsValidator::class)
        ->args([
            tagged_iterator('app.requirements_validator'),
            service('logger'),
            param('kernel.environment'),
        ]);

    $services->set(PermissionLifecycleService::class)
        ->args([
            service(Connection::class),
            service(Privileges::class),
            service(ClockInterface::class),
        ]);

    // App Lifecycle Persisters - do not change priority without careful consideration
    $services->set(FlowActionLifecycleHandler::class)
        ->args([
            service('app_flow_action.repository'),
            service(Connection::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => 0]);

    $services->set(WebhookLifecycleHandler::class)
        ->args([
            service(Connection::class),
            service(WebhookCacheClearer::class),
            service(ClockInterface::class),
            service(WebhookTargetValidator::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -100]);

    $services->set(FlowEventLifecycleHandler::class)
        ->args([
            service('app_flow_event.repository'),
            service(Connection::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -200]);

    $services->set(RuleConditionLifecycleHandler::class)
        ->args([
            service(ScriptFileReader::class),
            service('app_script_condition.repository'),
            service('app.repository'),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -700]);

    $services->set(ActionButtonLifecycleHandler::class)
        ->args([
            service('app_action_button.repository'),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -800]);

    $services->set(CmsBlockLifecycleHandler::class)
        ->args([
            service('app_cms_block.repository'),
            service(BlockTemplateLoader::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1200]);

    $services->set(TemplateLifecycleHandler::class)
        ->args([
            service(TemplateLoader::class),
            service('app_template.repository'),
            service('app.repository'),
            service(CacheClearer::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -900]);

    $services->set(ScriptLifecycleHandler::class)
        ->args([
            service(ScriptFileReader::class),
            service('script.repository'),
            service('app.repository'),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1000]);

    $services->set(CustomFieldLifecycleHandler::class)
        ->args([
            service(CustomFieldSetPersister::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1100]);

    $services->set(AppFeatureLifecycleHandler::class)
        ->args([
            service(AppFeatureDefinitionRegistry::class),
            service(AppFeatureStorage::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1300]);

    $services->set(AppFeatureDefinitionRegistry::class)
        ->args([
            tagged_iterator('contena.app_feature.definition'),
        ]);

    $services->set(AppFeatureStorage::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(AppFeatureDefinitionRegistry::class),
        ]);

    $services->set(ConsentFeatureDefinition::class)
        ->tag('contena.app_feature.definition');

    $services->set(AppConsentDefinitionProvider::class)
        ->args([
            service(AppFeatureStorage::class),
        ])
        // provided before the consents registered in the container, so an app cannot replace one of them
        ->tag('contena.consent.definition_provider', ['priority' => 100]);

    $services->set(ScriptFileReader::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(TemplateLoader::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(BlockTemplateLoader::class);

    $services->set(ContentSystemElementTypePersister::class)
        ->args([
            service('app_content_system_element_type.repository'),
            service(YamlTypeLoader::class),
            service(ElementTypeCollisionDetector::class),
            service(ContentSystemElementTypeRegistry::class),
            service(ElementTypeSpecificationSerializer::class),
            service(Connection::class),
            service('lock.factory'),
        ]);

    $services->set(ContentSystemElementTypeLifecycleHandler::class)
        ->args([
            service(ContentSystemElementTypePersister::class),
            service(ContentSystemElementTypeRegistry::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1400]);

    $services->set(ContentSystemElementTypeAppValidator::class)
        ->args([
            service(YamlTypeLoader::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(ContentSystemStyleOptionPersister::class)
        ->args([
            service('app_content_system_style_option.repository'),
            service(YamlStyleOptionLoader::class),
            service(StyleOptionCollisionDetector::class),
            service(ContentSystemStyleOptionRegistry::class),
            service(StyleOptionSpecificationSerializer::class),
            service(Connection::class),
            service('lock.factory'),
        ]);

    $services->set(ContentSystemStyleOptionLifecycleHandler::class)
        ->args([
            service(ContentSystemStyleOptionPersister::class),
            service(ContentSystemStyleOptionRegistry::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1401]);

    $services->set(ContentSystemStyleOptionAppValidator::class)
        ->args([
            service(YamlStyleOptionLoader::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(ContentSystemBindingSpecificationPersister::class)
        ->args([
            service(YamlBindingSpecificationLoader::class),
            service(YamlTypeLoader::class),
            service('app_content_system_binding_specification.repository'),
            service(BindingSpecificationSerializer::class),
            service(Connection::class),
            service(ContentSystemBindingSpecificationRegistry::class),
            service('lock.factory'),
        ]);

    $services->set(ContentSystemBindingSpecificationLifecycleHandler::class)
        ->args([
            service(ContentSystemBindingSpecificationPersister::class),
            service(ContentSystemBindingSpecificationRegistry::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1402]);

    $services->set(ContentSystemBindingSpecificationAppValidator::class)
        ->args([
            service(YamlBindingSpecificationLoader::class),
            service(YamlTypeLoader::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(ContentSystemLayoutPresetPersister::class)
        ->args([
            service('app_content_system_layout_preset.repository'),
            service(YamlLayoutPresetLoader::class),
            service(LayoutPresetSpecificationSerializer::class),
            service(ContentSystemLayoutPresetRegistry::class),
            service(Connection::class),
            service('lock.factory'),
        ]);

    $services->set(ContentSystemLayoutPresetLifecycleHandler::class)
        ->args([
            service(ContentSystemLayoutPresetPersister::class),
            service(ContentSystemLayoutPresetRegistry::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1403]);

    $services->set(ContentSystemLayoutPresetAppValidator::class)
        ->args([
            service(YamlLayoutPresetLoader::class),
        ])
        ->tag('contena.app_manifest.validator');

    $services->set(AppService::class)
        ->args([
            service(AppLifecycleIterator::class),
            service(AppLifecycle::class),
        ]);

    $services->set(AppPayloadServiceHelper::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
            service(InstallationIdProvider::class),
            env('APP_URL'),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(ActiveAppsLoader::class)
        ->args([
            service(Connection::class),
            service(AppLoader::class),
            param('kernel.project_dir'),
        ])
        ->tag('kernel.reset', ['method' => 'reset'])
        ->tag('kernel.event_listener', ['event' => 'console.terminate', 'method' => 'reset']);

    $services->set(AppCookieCollectListener::class)
        ->args([
            service(AppFeatureStorage::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(CookieFeatureDefinition::class)
        ->tag('contena.app_feature.definition');

    $services->set(ModuleFeatureDefinition::class)
        ->tag('contena.app_feature.definition');

    $services->set(AppRegistrationService::class)
        ->args([
            service(HandshakeFactory::class),
            service('contena.app_system.guzzle'),
            service('app.repository'),
            env('APP_URL'),
            service(InstallationIdProvider::class),
            param('kernel.contena_version'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(AppSecretRotationService::class)
        ->args([
            service(AppRegistrationService::class),
            service('app.repository'),
            service('integration.repository'),
            service('messenger.default_bus'),
            service('logger'),
            service(ManifestFactory::class),
            service(ClockInterface::class),
            service(DeletedAppsGateway::class),
        ]);

    $services->set(AppFeatureValidator::class)
        ->args([
            param('kernel.environment'),
        ]);

    $services->set(AppStorage::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(HandshakeFactory::class)
        ->args([
            env('APP_URL'),
            service(InstallationIdProvider::class),
            param('kernel.contena_version'),
            service(ClockInterface::class),
        ]);

    $services->set(AppManager::class)
        ->args([
            tagged_iterator('contena.app_lifecycle.handler'),
            service('app.repository'),
            service(PermissionLifecycleService::class),
            service('event_dispatcher'),
            service(AppRegistrationService::class),
            service(AppSecretRotationService::class),
            service(ManifestFactory::class),
            service(ActiveAppsLoader::class),
            service('language.repository'),
            service(SystemConfigService::class),
            service('integration.repository'),
            service('acl_role.repository'),
            service(AssetService::class),
            service(ScriptExecutor::class),
            param('kernel.project_dir'),
            service(CustomEntityLifecycleService::class),
            service(AppFeatureValidator::class),
            service(SourceResolver::class),
            service(ConfigReader::class),
            service(DeletedAppsGateway::class),
            service(ManifestValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(AppLifecycle::class)
        ->args([
            service(AppManager::class),
            service(AppStorage::class),
        ]);

    $services->set(AppLifecycleIterator::class)
        ->args([
            service('app.repository'),
            service(AppLoader::class),
        ]);

    $services->set(DeleteCascadeAppsTask::class)
        ->tag('contena.scheduled.task');

    $services->set(DeleteCascadeAppsHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('acl_role.repository'),
            service('integration.repository'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(RotateAppSecretHandler::class)
        ->args([
            service(AppSecretRotationService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(AppLoader::class)
        ->args([
            param('contena.app_dir'),
            service('logger'),
        ]);

    $services->set('contena.app_system.guzzle.middleware', AuthMiddleware::class)
        ->args([
            param('kernel.contena_version'),
            service(AppLocaleProvider::class),
        ]);

    $services->set('contena.app_system.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 5,
                'connect_timeout' => 1,
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->call('push', [
                        service('contena.app_system.guzzle.middleware'),
                    ]),
            ],
        ]);

    $services->set(ActionButtonLoader::class)
        ->args([
            service('app_action_button.repository'),
        ]);

    $services->set(ActionButtonResponseFactory::class)
        ->args([
            tagged_iterator('contena.action_button.response_factory'),
        ]);

    $services->set(NotificationResponseFactory::class)
        ->tag('contena.action_button.response_factory');

    $services->set(OpenModalResponseFactory::class)
        ->args([
            service(QuerySigner::class),
        ])
        ->tag('contena.action_button.response_factory');

    $services->set(OpenNewTabResponseFactory::class)
        ->args([
            service(QuerySigner::class),
        ])
        ->tag('contena.action_button.response_factory');

    $services->set(ReloadDataResponseFactory::class)
        ->tag('contena.action_button.response_factory');

    $services->set(QuerySigner::class)
        ->args([
            env('APP_URL'),
            param('kernel.contena_version'),
            service(AppLocaleProvider::class),
            service(InstallationIdProvider::class),
            service(ClockInterface::class),
        ]);

    $services->set(Executor::class)
        ->args([
            service('contena.app_system.guzzle'),
            service('logger'),
            service(ActionButtonResponseFactory::class),
            service(InstallationIdProvider::class),
            service('router'),
            service('request_stack'),
            service('kernel'),
            service(ClockInterface::class),
        ]);

    $services->set(AppActionLoader::class)
        ->args([
            service('app_action_button.repository'),
            service(AppPayloadServiceHelper::class),
        ]);

    $services->set(AppActionController::class)
        ->public()
        ->args([
            service(ActionButtonLoader::class),
            service(AppActionLoader::class),
            service(Executor::class),
            service(ModuleLoader::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AppCmsController::class)
        ->public()
        ->args([service('app_cms_block.repository')])
        ->call('setContainer', [service('service_container')]);

    $services->set(AppSecretRotationController::class)
        ->public()
        ->args([
            service('app.repository'),
            service(AppSecretRotationService::class),
        ]);

    $services->set(AppPrinter::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(AppLocaleProvider::class)
        ->public()
        ->args([
            service('user.repository'),
            service(LanguageLocaleCodeProvider::class),
        ]);

    // COMMANDS
    $services->set(RefreshAppCommand::class)
        ->args([
            service(AppService::class),
            service(AppPrinter::class),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(InstallAppCommand::class)
        ->args([
            service(AppLoader::class),
            service(AppLifecycle::class),
            service(AppPrinter::class),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(UninstallAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            service(AppStorage::class),
        ])
        ->tag('console.command');

    $services->set(ActivateAppCommand::class)
        ->args([
            service(AppStorage::class),
            service(AppLifecycle::class),
        ])
        ->tag('console.command');

    $services->set(DeactivateAppCommand::class)
        ->args([
            service(AppStorage::class),
            service(AppLifecycle::class),
        ])
        ->tag('console.command');

    $services->set(CreateAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            param('contena.app_dir'),
        ])
        ->tag('console.command');

    $services->set(ValidateAppCommand::class)
        ->args([
            param('contena.app_dir'),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(ChangeInstallationIdCommand::class)
        ->args([
            service(Resolver::class),
        ])
        ->tag('console.command');

    $services->set(AppListCommand::class)
        ->args([
            service(AppStorage::class),
        ])
        ->tag('console.command');

    $services->set(RotateAppSecretCommand::class)
        ->args([
            service('app.repository'),
            service(AppSecretRotationService::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('console.command');

    $services->set(InstallationIdController::class)
        ->public()
        ->args([
            service(Resolver::class),
            service(InstallationIdProvider::class),
            service('app.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(Resolver::class)
        ->public()
        ->args([
            tagged_iterator('contena.app_url_changed_resolver'),
        ]);

    $services->set(MoveInstallationPermanentlyStrategy::class)
        ->args([
            service('app.repository'),
            service(AppManager::class),
            service(InstallationIdProvider::class),
            service('logger'),
        ])
        ->tag('contena.app_url_changed_resolver', ['priority' => -100]);

    $services->set(ReinstallAppsStrategy::class)
        ->args([
            service('app.repository'),
            service(AppManager::class),
            service(InstallationIdProvider::class),
            service('logger'),
        ])
        ->tag('contena.app_url_changed_resolver', ['priority' => 100]);

    $services->set(UninstallAppsStrategy::class)
        ->args([
            service('app.repository'),
            service(InstallationIdProvider::class),
            service(AppManager::class),
        ])
        ->tag('contena.app_url_changed_resolver', ['priority' => 0]);

    // DELTA
    $services->set(PermissionsDeltaProvider::class)
        ->tag('contena.app_delta');

    $services->set(DomainsDeltaProvider::class)
        ->tag('contena.app_delta');

    // ENTITY DEFINITIONS
    $services->set(AppDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ActionButtonDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppCmsBlockDefinition::class)
        ->tag('contena.entity.definition');
    $services->set(AppCmsBlockTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ActionButtonTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(TemplateDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppScriptConditionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppScriptConditionTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppFlowActionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppFlowActionTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppFlowEventDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppContentSystemElementTypeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppContentSystemStyleOptionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppContentSystemBindingSpecificationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppContentSystemLayoutPresetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppFlowActionLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(AppFlowActionProvider::class)
        ->public()
        ->args([
            service(Connection::class),
            service(BusinessEventEncoder::class),
            service(StringTemplateRenderer::class),
        ]);

    $services->set(AppConfirmationDeltaProvider::class)
        ->args([
            tagged_iterator('contena.app_delta'),
        ]);

    $services->set(NoDatabaseSourceResolver::class)
        ->args([
            service(ActiveAppsLoader::class),
        ]);

    $services->set(SourceResolver::class)
        ->args([
            tagged_iterator('app.source_resolver'),
            service('app.repository'),
            service(NoDatabaseSourceResolver::class),
        ]);

    $services->set(RemoteZip::class)
        ->args([
            service(TemporaryDirectoryFactory::class),
            service(AppDownloader::class),
            service(AppExtractor::class),
        ])
        ->tag('app.source_resolver');

    $services->set(Local::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('app.source_resolver', ['priority' => -100]);

    $services->set(AppArchiveValidator::class);

    $services->set(AppExtractor::class)
        ->args([
            service(AppArchiveValidator::class),
        ]);

    $services->set(AppDownloader::class)
        ->args([
            service(HttpClientInterface::class),
        ]);

    $services->set(TemporaryDirectoryFactory::class);

    $services->set(AppTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.telemetry.subscriber');

    $services->set(Privileges::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(AppPrivilegeController::class)
        ->public()
        ->args([
            service(Connection::class),
            service(Privileges::class),
        ]);

    $services->set(ChannelDomainUrls::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.app_system.installation_id_fingerprint');

    $services->set(InstallationPath::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('contena.app_system.installation_id_fingerprint');

    $services->set(AppUrl::class)
        ->tag('contena.app_system.installation_id_fingerprint');

    $services->set(FingerprintGenerator::class)
        ->args([
            tagged_iterator('contena.app_system.installation_id_fingerprint'),
        ]);

    $services->set(CheckInstallationIdCommand::class)
        ->args([
            service(SystemConfigService::class),
            service(FingerprintGenerator::class),
        ])
        ->tag('console.command');

    $services->set(SystemHeartbeatTask::class)
        ->tag('contena.scheduled.task');

    $services->set(SystemHeartbeatHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeletedAppsGateway::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(AppSecretResolver::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(RememberDeletedAppsSecretSubscriber::class)
        ->args([
            service('app.repository'),
            service(DeletedAppsGateway::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppUrlVerifier::class)
        ->args([
            param('kernel.environment'),
            param('kernel.contena_version'),
            service('cache.app'),
            service(HttpClientInterface::class),
            service('lock.factory'),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(AppUrlVerificationPrinter::class)
        ->args([
            service(InstallationIdProvider::class),
        ]);

    $services->set(AppUrlVerificationStatusCommand::class)
        ->args([
            service(AppUrlVerifier::class),
            service(AppUrlVerificationPrinter::class),
        ])
        ->tag('console.command');

    $services->set(AppUrlVerifyCommand::class)
        ->args([
            service(InstallationIdProvider::class),
            service(AppUrlVerifier::class),
            service(AppUrlVerificationPrinter::class),
        ])
        ->tag('console.command');

    $services->set(VerifyInstallationController::class)
        ->public()
        ->args([
            service('contena.rate_limiter'),
            service(AppUrlVerifier::class),
        ]);
};

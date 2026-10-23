<?php declare(strict_types=1);

namespace Contena\Administration\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Administration\Command\CheckExtensionsCommand;
use Contena\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Contena\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Contena\Administration\Command\GenerateEntitySchemaTypesCommand;
use Contena\Administration\Command\SetupExtensionToolingCommand;
use Contena\Administration\Controller\AdminExtensionApiController;
use Contena\Administration\Controller\AdministrationController;
use Contena\Administration\Controller\AdminSearchController;
use Contena\Administration\Controller\AdminTagController;
use Contena\Administration\Controller\DashboardController;
use Contena\Administration\Controller\AdminUserConfigController;
use Contena\Administration\Dashboard\OrderAmountService;
use Contena\Administration\Framework\Adapter\Cache\Http\AdministrationCacheControlListener;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Contena\Administration\Service\AdminSearcher;
use Contena\Administration\Snippet\AdministrationSnippetService;
use Contena\Administration\Snippet\AppAdministrationSnippetDefinition;
use Contena\Administration\Snippet\AppAdministrationSnippetPersister;
use Contena\Administration\Snippet\AppLifecycleSubscriber;
use Contena\Administration\Snippet\CachedSnippetFinder;
use Contena\Administration\Snippet\SnippetFinder;
use Contena\Core\System\User\Validation\UserEmailUniqueChecker;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\ActionButton\Executor;
use Contena\Core\Framework\App\Hmac\QuerySigner;
use Contena\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Contena\Core\Framework\App\Source\SourceResolver;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Contena\Administration\Controller\AdminContentStreamController;
use Contena\Administration\System\Channel\Subscriber\ChannelAdminUserConfigSubscriber;
use Contena\Core\Framework\Notification\Api\NotificationController;
use Contena\Core\Framework\Notification\NotificationDefinition;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(CONTENA_ADMINISTRATION_PATH_NAME)', 'admin')
        ->set('contena_administration.path_name', env('CONTENA_ADMINISTRATION_PATH_NAME')->resolve());

    $services = $containerConfigurator->services();

    $services->set(DeleteAdminFilesAfterBuildCommand::class)
        ->args([
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(DeleteExtensionLocalPublicFilesCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(CheckExtensionsCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(SetupExtensionToolingCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(GenerateEntitySchemaTypesCommand::class)
        ->tag('console.command');

    $services->set(AdminExtensionApiController::class)
        ->public()
        ->args([
            service(Executor::class),
            service(AppPayloadServiceHelper::class),
            service('app.repository'),
            service(QuerySigner::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdministrationController::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service(FirstRunWizardService::class),
            service(AdministrationSnippetService::class),
            param('kernel.supported_api_versions'),
            service(KnownIpsCollector::class),
            service(Connection::class),
            service('event_dispatcher'),
            param('kernel.contena_core_dir'),
            service('user.repository'),
            service('currency.repository'),
            service(HtmlSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service('parameter_bag'),
            service('contena.filesystem.asset'),
            param('contena.service_registry.url'),
            service('language.repository'),
            env('BLOG_ANALYTICS_GATEWAY_URL'),
            service(UserEmailUniqueChecker::class),
            param('contena.api.refresh_token_ttl'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminSearchController::class)
        ->public()
        ->args([
            service(RequestCriteriaBuilder::class),
            service(DefinitionInstanceRegistry::class),
            service(AdminSearcher::class),
            service('serializer'),
            service(AclCriteriaValidator::class),
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminUserConfigController::class)
        ->public()
        ->args([
            service('admin_user_config.repository'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminContentStreamController::class)
        ->public()
        ->args([
            service(BlogDefinition::class),
            service('channel.blog.repository'),
            service(ChannelContextService::class),
            service(RequestCriteriaBuilder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminTagController::class)
        ->public()
        ->args([
            service(FilterTagIdsService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(
        'Contena\Administration\Controller\NotificationController',
        NotificationController::class,
    )
        ->public()
        ->deprecate('contena/administration', '6.7.15.0', 'The "%alias_id%" service alias is deprecated and will be removed in v6.8.0. Use Contena\Core\Framework\Notification\Api\NotificationController instead.');

    $services->set(AdminSearcher::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AppAdministrationSnippetDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AppAdministrationSnippetPersister::class)
        ->args([
            service('app_administration_snippet.repository'),
            service('locale.repository'),
            service(CacheInvalidator::class),
            service(Filesystem::class),
        ]);

    $services->set(SnippetFinder::class)
        ->args([
            service('kernel'),
            service(Connection::class),
            service('contena.filesystem.translation'),
            service('contena.filesystem.private'),
            service(TranslationConfig::class),
            service(TranslationLoader::class),
            service(HtmlSanitizer::class),
            service('logger'),
            param('kernel.debug'),
        ]);

    $services->set(CachedSnippetFinder::class)
        ->decorate(SnippetFinder::class)
        ->args([
            service(CachedSnippetFinder::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(AdministrationSnippetService::class)
        ->args([
            service(SnippetFinder::class),
            service(SymfonyBearerTokenValidator::class),
        ]);

    $services->set(AppLifecycleSubscriber::class)
        ->args([
            service(SourceResolver::class),
            service(AppAdministrationSnippetPersister::class),
        ])
        ->tag('kernel.event_subscriber');

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(
        'Contena\Administration\Notification\NotificationDefinition',
        NotificationDefinition::class,
    )->deprecate('contena/administration', '6.7.15.0', 'The "%alias_id%" service alias is deprecated and will be removed in v6.8.0. Use Contena\Core\Framework\Notification\NotificationDefinition instead.');

    $services->set(ChannelAdminUserConfigSubscriber::class)
        ->args([
            service('admin_user_config.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderAmountService::class)
        ->args([
            service(Connection::class),
            param('contena.dbal.time_zone_support_enabled'),
        ]);

    $services->set(DashboardController::class)
        ->public()
        ->args([
            service(OrderAmountService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdministrationCacheControlListener::class)
        ->tag('kernel.event_listener', ['event' => BeforeCacheControlEvent::class])
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);
};

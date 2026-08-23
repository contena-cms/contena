<?php declare(strict_types=1);

namespace Contena\Administration\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Administration\Command\CheckExtensionsCommand;
use Contena\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Contena\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Contena\Administration\Command\GenerateEntitySchemaTypesCommand;
use Contena\Administration\Command\SetupExtensionToolingCommand;
use Contena\Administration\Controller\AdministrationController;
use Contena\Administration\Controller\AdminSearchController;
use Contena\Administration\Controller\AdminTagController;
use Contena\Administration\Controller\UserConfigController;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Contena\Administration\Service\AdminSearcher;
use Contena\Administration\Snippet\CachedSnippetFinder;
use Contena\Administration\Snippet\SnippetFinder;
use Contena\Administration\System\Channel\Subscriber\ChannelUserConfigSubscriber;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

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

    $services->set(AdministrationController::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service(SnippetFinder::class),
            param('kernel.supported_api_versions'),
            service(KnownIpsCollector::class),
            service(HtmlSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service('contena.filesystem.asset'),
            service('language.repository'),
            service(SymfonyBearerTokenValidator::class),
            service(Connection::class),
            service('event_dispatcher'),
            param('kernel.contena_core_dir'),
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

    $services->set(UserConfigController::class)
        ->public()
        ->args([
            service('user_config.repository'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminTagController::class)
        ->public()
        ->args([
            service(FilterTagIdsService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminSearcher::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(SnippetFinder::class)
        ->args([
            service('kernel'),
            service('contena.filesystem.translation'),
            service(TranslationConfig::class),
            service(TranslationLoader::class),
            service('logger'),
            param('kernel.debug'),
        ]);

    $services->set(CachedSnippetFinder::class)
        ->decorate(SnippetFinder::class)
        ->args([
            service(CachedSnippetFinder::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(ChannelUserConfigSubscriber::class)
        ->args([
            service('user_config.repository'),
        ])
        ->tag('kernel.event_subscriber');
};

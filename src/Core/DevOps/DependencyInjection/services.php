<?php declare(strict_types=1);

namespace Contena\Core\DevOps\DependencyInjection;

use Contena\Core\DevOps\Docs\App\DocsAppEventCommand;
use Contena\Core\DevOps\Docs\Script\HooksReferenceGenerator;
use Contena\Core\DevOps\Docs\Script\ScriptReferenceGeneratorCommand;
use Contena\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Contena\Core\DevOps\Docs\Script\TriggerReferenceGeneratorCommand;
use Contena\Core\DevOps\System\Command\OpenApiValidationCommand;
use Contena\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Webhook\Authorization\Policy\PolicyRegistry;
use Contena\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire();

    $services->set(SyncComposerVersionCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(DocsAppEventCommand::class)
        ->args([
            service(BusinessEventCollector::class),
            service(HookableEventCollector::class),
            service(PolicyRegistry::class),
            tagged_iterator('contena.hookable_event.describer'),
            service('twig'),
        ])
        ->tag('console.command');

    $services->set(ScriptReferenceGeneratorCommand::class)
        ->args([
            tagged_iterator('contena.scripts_reference.generator'),
        ])
        ->tag('console.command');

    $services->set(TriggerReferenceGeneratorCommand::class)
        ->args([
            service(BusinessEventCollector::class),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(HooksReferenceGenerator::class)
        ->args([
            service('service_container'),
            service('twig'),
            service(ServiceReferenceGenerator::class),
        ])
        ->tag('contena.scripts_reference.generator');

    $services->set(ServiceReferenceGenerator::class)
        ->args([
            service('twig'),
            param('kernel.project_dir'),
        ])
        ->tag('contena.scripts_reference.generator');

    $services->set(OpenApiValidationCommand::class)
        ->args([
            service(HttpClientInterface::class),
            service(DefinitionService::class),
        ])
        ->tag('console.command');
};

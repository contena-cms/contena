<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Contena\Core\Framework\Script\Api\AclFacadeHookFactory;
use Contena\Core\Framework\Script\Api\ScriptApiRoute;
use Contena\Core\Framework\Script\Api\ScriptChannelApiRoute;
use Contena\Core\Framework\Script\Api\ScriptResponseEncoder;
use Contena\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Contena\Core\Framework\Script\AppContextCreator;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\Framework\Script\Execution\ScriptLoader;
use Contena\Core\Framework\Script\ScriptDefinition;
use Contena\Core\System\Channel\Api\StructEncoder;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ScriptLoader::class)
        ->args([
            service(Connection::class),
            service(ScriptLifecycleHandler::class),
            service('cache.object'),
            param('twig.cache'),
            param('kernel.debug'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScriptExecutor::class)
        ->public()
        ->args([
            service(ScriptLoader::class),
            service(ScriptTraces::class),
            service('service_container'),
            service(ScriptEnvironmentFactory::class),
        ]);

    $services->set(ScriptEnvironmentFactory::class)
        ->public()
        ->args([
            service('twig.extension.debug'),
            tagged_iterator('contena.app_script.twig.extension'),
            param('kernel.contena_version'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScriptDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ScriptTraces::class)
        ->public()
        ->args([
            service(ClockInterface::class),
        ])
        ->tag('data_collector')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScriptChannelApiRoute::class)
        ->public()
        ->args([
            service(ScriptExecutor::class),
            service(ScriptResponseEncoder::class),
            service('cache.object'),
            service('logger'),
        ]);

    $services->set(ScriptApiRoute::class)
        ->public()
        ->args([
            service(ScriptExecutor::class),
            service(ScriptLoader::class),
            service(ScriptResponseEncoder::class),
        ]);

    $services->set(ScriptResponseFactoryFacadeHookFactory::class)
        ->public()
        ->args([
            service('router'),
        ]);

    $services->set(ScriptResponseEncoder::class)
        ->args([
            service(StructEncoder::class),
        ]);

    $services->set(AclFacadeHookFactory::class)
        ->public()
        ->args([
            service(AppContextCreator::class),
        ]);

    $services->set(AppContextCreator::class)
        ->args([
            service(Connection::class),
        ]);
};

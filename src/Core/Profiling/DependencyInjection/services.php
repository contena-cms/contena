<?php declare(strict_types=1);

namespace Contena\Core\Profiling\DependencyInjection;

use Contena\Core\Framework\Adapter\Command\CacheWatchDelayedCommand;
use Contena\Core\Profiling\Profiler;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Contena\Core\Profiling\Integration\Datadog;
use Contena\Core\Profiling\Integration\ServerTiming;
use Contena\Core\Profiling\Integration\Tideways;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();


    $services->set(Tideways::class)
        ->tag('contena.profiler', ['integration' => 'Tideways']);

    $services->set(CacheWatchDelayedCommand::class)
        ->tag('console.command')
        ->args([
            service('service_container'),
        ]);

    $services->set(Datadog::class)
        ->tag('contena.profiler', ['integration' => 'Datadog']);

    $services->set(ServerTiming::class)
        ->tag('contena.profiler', ['integration' => 'ServerTiming'])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onResponseEvent']);

    $services->set(Profiler::class)
        ->public()
        ->args([
            tagged_iterator('contena.profiler', 'integration'),
            param('contena.profiler.integrations'),
        ]);
};

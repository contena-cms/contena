<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Contena\Core\System\StateMachine\Api\StateMachineActionController;
use Contena\Core\System\StateMachine\Command\WorkflowDumpCommand;
use Contena\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Contena\Core\System\StateMachine\StateMachineDefinition;
use Contena\Core\System\StateMachine\StateMachineLocker;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\StateMachineTranslationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(StateMachineActionController::class)
        ->public()
        ->args([
            service(StateMachineRegistry::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(StateMachineRegistry::class)
        ->args([
            service('state_machine.repository'),
            service('state_machine_state.repository'),
            service('state_machine_history.repository'),
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(StateMachineLocker::class),
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(StateMachineLocker::class)
        ->args([
            service('lock.factory'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(WorkflowDumpCommand::class)
        ->args([
            service(StateMachineRegistry::class),
        ])
        ->tag('console.command');

    $services->set(StateMachineDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(StateMachineTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(StateMachineStateDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(StateMachineStateTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(StateMachineTransitionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(StateMachineHistoryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(InitialStateIdLoader::class)
        ->args([
            service(Connection::class),
            service('cache.object'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
};
